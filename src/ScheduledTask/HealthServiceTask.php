<?php declare(strict_types=1);

namespace ASOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class HealthServiceTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'as.health_service_task';
    }

    public static function getDefaultInterval(): int
    {
        return 3540; // 59minutes
    }
}