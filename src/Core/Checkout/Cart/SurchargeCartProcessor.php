<?php declare(strict_types=1);

namespace Topdata\TopdataSurchargeSW6\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SurchargeCartProcessor implements CartProcessorInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $taxRepository,
    ) {
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $salesChannelId = $context->getSalesChannelId();

        $isActive = $this->systemConfigService->getBool('TopdataSurchargeSW6.config.isActive', $salesChannelId);
        $percentage = $this->systemConfigService->getFloat('TopdataSurchargeSW6.config.surchargePercentage', $salesChannelId);
        $name = $this->systemConfigService->getString('TopdataSurchargeSW6.config.surchargeName', $salesChannelId);
        $taxId = $this->systemConfigService->getString('TopdataSurchargeSW6.config.taxId', $salesChannelId);
        $surchargeBasis = $this->systemConfigService->getString('TopdataSurchargeSW6.config.surchargeBasis', $salesChannelId);

        if (!$isActive || $percentage <= 0.0) {
            return;
        }

        if (trim($name) === '') {
            $name = 'Service-Aufschlag';
        }

        $products = $toCalculate->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        if ($products->count() === 0) {
            return;
        }

        $surchargeId = '06a3e52f7afc42d69e255068886f1d22';
        if ($toCalculate->has($surchargeId)) {
            return;
        }

        if (empty($taxId)) {
            return;
        }

        $criteria = new Criteria([$taxId]);
        $taxEntity = $this->taxRepository->search($criteria, $context->getContext())->first();

        if ($taxEntity === null) {
            return;
        }

        $taxRate = (float) $taxEntity->getTaxRate();

        $totalGross = 0.0;
        $totalNet = 0.0;
        foreach ($products->getPrices() as $price) {
            $totalGross += $price->getTotalPrice();
            $totalNet += $price->getTotalPrice() - $price->getCalculatedTaxes()->getAmount();
        }

        $baseAmount = $surchargeBasis === 'net' ? $totalNet : $totalGross;
        $rawSurcharge = $baseAmount * ($percentage / 100.0);

        if ($surchargeBasis === 'gross') {
            $totalPrice = round($rawSurcharge, 2);
            $netPrice = round($totalPrice / (1 + $taxRate / 100), 2);
            $taxAmount = round($totalPrice - $netPrice, 2);
        } else {
            $netPrice = round($rawSurcharge, 2);
            $taxAmount = round($netPrice * ($taxRate / 100), 2);
            $totalPrice = round($netPrice + $taxAmount, 2);
        }

        $calculatedTax = new CalculatedTax($taxAmount, $taxRate, $netPrice);
        $calculatedPrice = new CalculatedPrice(
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection([$calculatedTax]),
            new TaxRuleCollection([new TaxRule($taxRate)])
        );

        $surchargeItem = new LineItem($surchargeId, 'surcharge', null, 1);
        $surchargeItem->setLabel($name);
        $surchargeItem->setGood(false);
        $surchargeItem->setRemovable(false);
        $surchargeItem->setPrice($calculatedPrice);

        $toCalculate->add($surchargeItem);
    }
}
