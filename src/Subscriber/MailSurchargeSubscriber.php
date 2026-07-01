<?php declare(strict_types=1);

namespace Topdata\TopdataSurchargeSW6\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[Package('checkout')]
class MailSurchargeSubscriber implements EventSubscriberInterface
{
    private EntityRepository $lineItemRepo;

    public function __construct(EntityRepository $lineItemRepo)
    {
        $this->lineItemRepo = $lineItemRepo;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'onMailBeforeValidate',
        ];
    }

    public function onMailBeforeValidate(MailBeforeValidateEvent $event): void
    {
        $templateData = $event->getTemplateData();

        $label = null;
        $total = null;

        if (isset($templateData['order']) && is_object($templateData['order'])) {
            $order = $templateData['order'];

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));
            $criteria->addFilter(new EqualsFilter('type', 'surcharge'));
            $criteria->setLimit(1);

            $result = $this->lineItemRepo->search($criteria, $event->getContext());
            if ($result->count() > 0) {
                $surchargeItem = $result->first();
                $label = $surchargeItem->getLabel();
                $total = $surchargeItem->getTotalPrice();
            }

            $lineItems = $order->getLineItems();
            if ($lineItems !== null) {
                $filtered = [];
                foreach ($lineItems as $item) {
                    if ($item->getType() !== 'surcharge') {
                        $filtered[] = $item;
                    }
                }
                $order->setLineItems(new OrderLineItemCollection(array_values($filtered)));
            }
        }

        $templateData['surchargeLabel'] = $label;
        $templateData['surchargeTotal'] = $total;

        $event->setTemplateData($templateData);
    }
}
