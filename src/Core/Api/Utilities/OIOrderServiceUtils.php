<?php

declare(strict_types=1);

namespace ASOrderInterface\Core\Api\Utilities;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\ParameterBag;

/*

To prevent overloaded classes and preserve clarity the functionality to change the current status of delivery and shipment
has been moved to its own class.

*/

class OIOrderServiceUtils
{
    /** @var OrderService $orderService */
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    //process, complete, cancel, reopen
    public function updateOrderStatus(OrderEntity $order, string $entityID, $transition)
    {
        $stateName = $order->getStateMachineState()->getTechnicalName();
        switch ($stateName) {
            case "open":
                if (!(strcmp($transition, 'cancel') == 0 || strcmp($transition, 'process') == 0)) {
                    return false;
                }
                break;

            case "in_progress":
                if (!(strcmp($transition, 'cancel') == 0 || strcmp($transition, 'complete') == 0)) {
                    return false;
                }
                break;

            case "completed":
                if (!(strcmp($transition, 'reopen') == 0)) {
                    return false;
                }
                break;

            case "cancelled":
                if (!(strcmp($transition, 'reopen') == 0)) {
                    return false;
                }
                break;
        }
        $this->orderService->orderStateTransition($entityID, $transition, new ParameterBag([]), Context::createDefaultContext());
        return true;
    }

    //ship, ship_partially, retour, retour_partially, cancel, reopen
    public function updateOrderDeliveryStatus(OrderDeliveryEntity $orderDelivery, string $entityID, string $transition): bool
    {
        switch ($orderDelivery->getStateMachineState()->getTechnicalName()) {
            case "open":
                if (!(strcmp($transition, 'cancel') == 0 || strcmp($transition, 'ship') == 0 || strcmp($transition, 'ship_partially') == 0)) {
                    return false;
                }
                break;

            case "shipped":
                if (!(strcmp($transition, 'cancel') == 0 || strcmp($transition, 'retour') == 0 || strcmp($transition, 'retour_partially') == 0)) {
                    return false;
                }
                break;

            case "shipped_partially":
                if (!(strcmp($transition, 'cancel') == 0 || strcmp($transition, 'retour') == 0 || strcmp($transition, 'retour_partially') == 0 || strcmp($transition, 'ship') == 0)) {
                    return false;
                }
                break;

            case "refunded_partially":
                return false;
                break;

            case "returned":
                return false;
                break;

            case "cancelled":
                if (!(strcmp($transition, 'reopen') == 0)) {
                    return false;
                }
                break;
        }
        $this->orderService->orderDeliveryStateTransition($entityID, $transition, new ParameterBag([]), Context::createDefaultContext());
        return true;
    }

    public function updatePaymentStatus(OrderTransactionEntity $transaction, string $entityID, $transition)
    {
        $stateName = $transaction->getStateMachineState()->getTechnicalName();
        switch ($stateName) {
            case "open":
                if (!(strcmp($transition, 'pay') == 0 || strcmp($transition, 'remind') == 0 || strcmp($transition, 'pay_partially') == 0 || strcmp($transition, 'cancel') == 0)) {

                    return false;
                }
                break;

            case "paid_partially":
                if (!(strcmp($transition, 'refund_partially') == 0 || strcmp($transition, 'refund') == 0 || strcmp($transition, 'remind') == 0 || strcmp($transition, 'pay') == 0)) {
                    return false;
                }
                break;

            case "reminded":
                if (!(strcmp($transition, 'pay') == 0 || strcmp($transition, 'pay_partially') == 0)) {
                    return false;
                }
                break;

            case "paid":
                if (!(strcmp($transition, 'refund_partially') == 0 || strcmp($transition, 'refund') == 0)) {
                    return false;
                }
                break;

            case "refunded_partially":
                if (!(strcmp($transition, 'refund') == 0)) {
                    return false;
                }
                break;
        }
        $this->orderService->orderTransactionStateTransition($entityID, $transition, new ParameterBag([]), Context::createDefaultContext());
        return true;
    }
}
