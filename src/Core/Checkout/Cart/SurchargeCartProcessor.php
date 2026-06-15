<?php declare(strict_types=1);

namespace Topdata\TopdataSurchargeSW6\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SurchargeCartProcessor implements CartProcessorInterface
{
    private PercentagePriceCalculator $calculator;
    private SystemConfigService $systemConfigService;

    // Wir injizieren jetzt auch den SystemConfigService
    public function __construct(
        PercentagePriceCalculator $calculator,
        SystemConfigService $systemConfigService
    ) {
        $this->calculator = $calculator;
        $this->systemConfigService = $systemConfigService;
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $salesChannelId = $context->getSalesChannelId();

        // 1. Konfiguration auslesen (PluginName.config.feldName)
        $isActive = $this->systemConfigService->getBool('TopdataSurchargeSW6.config.isActive', $salesChannelId);
        $percentage = $this->systemConfigService->getFloat('TopdataSurchargeSW6.config.surchargePercentage', $salesChannelId);
        $name = $this->systemConfigService->getString('TopdataSurchargeSW6.config.surchargeName', $salesChannelId);

        // Wenn nicht aktiv oder 0%, brich direkt ab
        if (!$isActive || $percentage <= 0.0) {
            return;
        }

        // Falls das Namensfeld leer gelassen wurde, setze einen Fallback
        if (trim($name) === '') {
            $name = 'Service-Aufschlag';
        }

        // 2. Produkte prüfen
        $products = $toCalculate->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        if ($products->count() === 0) {
            return;
        }

        $surchargeId = '06a3e52f7afc42d69e255068886f1d22';

        // 3. Verhindern, dass er doppelt hinzugefügt wird
        if ($toCalculate->has($surchargeId)) {
            return;
        }

        // 4. Neue Position erstellen
        $surchargeItem = new LineItem($surchargeId, 'surcharge', null, 1);

        // Hier setzen wir jetzt den dynamischen Namen aus dem Backend
        $surchargeItem->setLabel($name);
        $surchargeItem->setGood(false);
        $surchargeItem->setRemovable(false);

        // Hier setzen wir die dynamische Prozentzahl
        $definition = new PercentagePriceDefinition($percentage);
        $surchargeItem->setPriceDefinition($definition);

        // 5. Preis berechnen
        $calculatedPrice = $this->calculator->calculate(
            $definition->getPercentage(),
            $products->getPrices(),
            $context
        );

        $surchargeItem->setPrice($calculatedPrice);

        // 6. Dem Warenkorb hinzufügen
        $toCalculate->add($surchargeItem);
    }
}
