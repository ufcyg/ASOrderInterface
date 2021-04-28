<?php

declare(strict_types=1);

namespace ASOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledSubmitArticlebaseTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'as.scheduled_submit_articlebase_task';
    }

    public static function getDefaultInterval(): int
    {
        return 86340; // daily
    }
}
