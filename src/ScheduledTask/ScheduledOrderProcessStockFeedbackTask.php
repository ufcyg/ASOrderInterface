<?php declare(strict_types=1);

namespace ASOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledOrderProcessStockFeedbackTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'synlab.scheduled_order_process_stock_feedback';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 5minutes
    }
}