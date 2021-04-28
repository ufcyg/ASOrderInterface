<?php

declare(strict_types=1);

namespace ASOrderInterface\Subscriber;

use ASOrderInterface\Core\Api\OrderInterfaceController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductEventSubscriber implements EventSubscriberInterface
{
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    /** @var OrderInterfaceController $asOrderInterfaceController */
    private $asOrderInterfaceController;

    public function __construct(
        SystemConfigService $systemConfigService,
        OrderInterfaceController $asOrderInterfaceController
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->asOrderInterfaceController = $asOrderInterfaceController;
    }
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            'product.written' => 'onProductWrittenEvent',
            'product.deleted' => 'onProductDeletedEvent',
        ];
    }

    public function onProductWrittenEvent(EntityWrittenEvent $event)
    {
        $eventArray = $event->getWriteResults();
        $this->asOrderInterfaceController->initStockQS(Context::createDefaultContext());
    }

    public function onProductDeletedEvent(EntityWrittenEvent $event)
    {
        $eventArray = $event->getWriteResults();
        $this->asOrderInterfaceController->deleteStockQSEntry($eventArray[0]->getPrimaryKey(), Context::createDefaultContext());
    }
}
