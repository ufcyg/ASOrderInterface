<?php declare(strict_types=1);

namespace ASOrderInterface\Core\Content\CancelledConfirmation;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderInterfaceCancelConfirmationEntity extends Entity
{
    use EntityIdTrait;
    
    /**
     * @var string
     */
    protected $orderId;

    /**
     * Get the value of orderId
     *
     * @return  string
     */ 
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * Set the value of orderId
     *
     * @param  string  $orderId
     *
     * @return  self
     */ 
    public function setOrderId(string $orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }
}