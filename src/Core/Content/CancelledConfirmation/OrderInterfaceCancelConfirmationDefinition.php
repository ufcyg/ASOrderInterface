<?php

declare(strict_types=1);

namespace ASOrderInterface\Core\Content\CancelledConfirmation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

class OrderInterfaceCancelConfirmationDefinition extends EntityDefinition
{

    public function getEntityName(): string
    {
        return 'as_cancelled_confirmation';
    }

    public function getCollectionClass(): string
    {
        return OrderInterfaceCancelConfirmationCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderInterfaceCancelConfirmationEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                new StringField('order_id', 'orderId')
            ]
        );
    }
}

// $connection->exec("CREATE TABLE IF NOT EXISTS `as_cancelled_confirmation` (
//     `id`            BINARY(16) NOT NULL,
//     `orderid`    VARCHAR(255) NOT NULL,
//     `created_at`    DATETIME(3),
//     `updated_at`    DATETIME(3)
//     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");