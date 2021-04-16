<?php declare(strict_types=1);

namespace ASOrderInterface\Core\Content\StockQS;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

class OrderInterfaceStockQSDefinition extends EntityDefinition
{

    public function getEntityName(): string
    {
        return 'as_stock_qs';
    }

    public function getCollectionClass(): string
    {
        return OrderInterfaceStockQSCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderInterfaceStockQSEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id','id'))->addFlags(new Required(), new PrimaryKey()),
                new StringField('product_name','productName'),
                new StringField('product_number','productNumber'),
                new StringField('product_id','productId'),
                new IntField('faulty','faulty'),
                new IntField('clarification','clarification'),
                new IntField('postprocessing','postprocessing'),
                new IntField('other','other')
            ]
        );
    }
}

// $connection->exec("CREATE TABLE IF NOT EXISTS `as_stock_qs` (
//     `id`            BINARY(16) NOT NULL,
//     `product_id`    VARCHAR(255) NOT NULL,
//     `faulty`    INTEGER NOT NULL,
//     `clarification`    INTEGER NOT NULL,
//     `postprocessing`    INTEGER NOT NULL,
//     `expired_mhd`    INTEGER NOT NULL,
//     `other`    INTEGER NOT NULL,
//     `created_at`    DATETIME(3),
//     `updated_at`    DATETIME(3)
//     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");