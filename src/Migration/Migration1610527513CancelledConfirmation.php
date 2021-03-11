<?php declare(strict_types=1);

namespace ASOrderInterface\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610527513CancelledConfirmation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610527513;
    }

    public function update(Connection $connection): void
    {
        $connection->exec("CREATE TABLE IF NOT EXISTS `as_cancelled_confirmation` (
            `id`            BINARY(16) NOT NULL,
            `order_id`    VARCHAR(255) NOT NULL,
            `created_at`    DATETIME(3),
            `updated_at`    DATETIME(3)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
