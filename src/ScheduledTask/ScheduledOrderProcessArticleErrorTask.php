<?php

declare(strict_types=1);

namespace ASOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledOrderProcessArticleErrorTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'as.scheduled_order_process_article_error';
    }

    public static function getDefaultInterval(): int
    {
        return 240; // 4minutes
    }
}
