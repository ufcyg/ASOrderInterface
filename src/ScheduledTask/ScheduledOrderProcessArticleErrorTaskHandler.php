<?php declare(strict_types=1);

namespace ASOrderInterface\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use ASOrderInterface\Core\Api\OrderInterfaceController;


class ScheduledOrderProcessArticleErrorTaskHandler extends ScheduledTaskHandler
{
    /** @var OrderInterfaceController $interfaceController */
    private $interfaceController;
    
    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, OrderInterfaceController $interfaceController)
    {
        $this->interfaceController = $interfaceController;
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        return [ ScheduledOrderProcessArticleErrorTask::class ];
    }

    public function run(): void
    {
        // $this->interfaceController->pullArticleError(Context::createDefaultContext());
    }    
}