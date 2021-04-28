<?php

declare(strict_types=1);

namespace ASOrderInterface\Core\Content\CancelledConfirmation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(OrderInterfaceCancelConfirmationCollection $entity)
 * @method void              set(string $key, OrderInterfaceCancelConfirmationCollection $entity)
 * @method OrderInterfaceCancelConfirmationCollection[]    getIterator()
 * @method OrderInterfaceCancelConfirmationCollection[]    getElements()
 * @method OrderInterfaceCancelConfirmationCollection|null get(string $key)
 * @method OrderInterfaceCancelConfirmationCollection|null first()
 * @method OrderInterfaceCancelConfirmationCollection|null last()
 */
class OrderInterfaceCancelConfirmationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderInterfaceCancelConfirmationEntity::class;
    }
}
