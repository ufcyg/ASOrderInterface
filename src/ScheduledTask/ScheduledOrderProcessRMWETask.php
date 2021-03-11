<?php declare(strict_types=1);

namespace ASOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledOrderProcessRMWETask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'as.scheduled_order_process_rmwe';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // 5minutes
    }
}