<?php declare(strict_types=1);

namespace ASOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledOrderTransferTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'as.scheduled_order_transfer_task';
    }

    public static function getDefaultInterval(): int
    {
        return 86340; // daily
    }
}