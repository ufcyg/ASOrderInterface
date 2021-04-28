<?php

declare(strict_types=1);

namespace ASOrderInterface\Core\Content\StockQS;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(SteeredCustomerRegistrationCollection $entity)
 * @method void              set(string $key, SteeredCustomerRegistrationCollection $entity)
 * @method SteeredCustomerRegistrationCollection[]    getIterator()
 * @method SteeredCustomerRegistrationCollection[]    getElements()
 * @method SteeredCustomerRegistrationCollection|null get(string $key)
 * @method SteeredCustomerRegistrationCollection|null first()
 * @method SteeredCustomerRegistrationCollection|null last()
 */
class OrderInterfaceStockQSCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderInterfaceStockQSEntity::class;
    }
}
