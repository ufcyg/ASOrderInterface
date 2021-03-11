<?php declare(strict_types=1);

/*

OrderInterfaceUtils holds most helper functions for the order interface controller.

*/

namespace ASOrderInterface\Core\Api\Utilities;

use Psr\Container\ContainerInterface;
use DateInterval;
use DateTime;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use ASOrderInterface\Core\Content\StockQS\OrderInterfaceStockQSEntity;

class OrderInterfaceUtils
{
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    /** @var string $folderRoot */
    private $folderRoot;
    /** @var ContainerInterface $container */
    protected $container;
    
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        $this->folderRoot = $this->systemConfigService->get('ASOrderInterface.config.workingDirectory');
    }
    

    /**
     * @internal
     * @required
     */
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $previous = $this->container;
        $this->container = $container;

        return $previous;
    }

    public function containerTest()
    {
        /** @var EntityRepositoryInterface $deadMessagesRepo */
        $deadMessagesRepo = $this->container->get('product.repository');
    }
    /* Gets an already created criteria and extends it with a date filter*/
    public function addCriteriaFilterDate(Criteria $criteria): Criteria
    {
        $yesterday = $this->createDateFromString('yesterday');

        $now = $this->createDateFromString('now');

        $criteria->addFilter(new RangeFilter('orderDate', [
            RangeFilter::GTE => $yesterday,
            RangeFilter::LTE => $now
        ]));
        return $criteria;
    }

    /* Creates a timestamp that will be used to filter by this date */
    public function createDateFromString(string $daytime): string
    {
        $timeStamp = new DateTime();
        $timeStamp->add(DateInterval::createFromDateString($daytime));
        $timeStamp = $timeStamp->format('Y-m-d H:i:s.u');
        $timeStamp = substr($timeStamp, 0, strlen($timeStamp) - 3);

        return $timeStamp;
    }

    /* Creates a timeStamp that will be attached to the end of the filename */
    public function createShortDateFromString(string $daytime): string
    {
        $timeStamp = new DateTime();
        $timeStamp->add(DateInterval::createFromDateString($daytime));
        $timeStamp = $timeStamp->format('Y-m-d_H-i-s_u');
        $timeStamp = substr($timeStamp, 0, strlen($timeStamp) - 3);

        return $timeStamp;
    }

    /* Creates a path according to the input $path and the preset folderRoot combined with todays date */
    public function createTodaysFolderPath($path, &$timeStamp):string
    {
        $timeStamp = new DateTime();
        $timeStamp = $timeStamp->format('d-m-Y');
         
        return $this->folderRoot . $path . '/' . $timeStamp;
    }

    /* Writes the current order to disc with a unique name depending on the orderID */
    public function writeOrder(string $orderNumber,string $folderPath, string $fileContent, string $companyID): string
    {
        $folderPath = $folderPath . '/' . $orderNumber . '/';

        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }

        $filePath = $folderPath . $companyID . '-' . $orderNumber . '-order.csv';
        file_put_contents($filePath,$fileContent);
        return $filePath;
    }

    /* Returns a search result containing all products in the products repository*/
    public function getAllProducts(Context $context): EntitySearchResult
    {
        $productsRepository = $this->container->get('product.repository');
        $criteria = new Criteria();
        /** @var EntitySearchResult */
        return $productsRepository->search($criteria, $context);
    }
    /* Returns a specific product defined by the articleNumber */
    public function getProduct(EntityRepositoryInterface $productRepository, string $articleNumber, Context $context): ?ProductEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $articleNumber));

        $searchResult = $productRepository->search($criteria,$context);
        return $searchResult->first();
    }
    /* Returns a specific stock qs entry defined by the productId */
    public function getStockQSEntity(EntityRepositoryInterface $stockQSEntity, string $productID, Context $context): OrderInterfaceStockQSEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productID));

        $searchResult = $stockQSEntity->search($criteria,$context);
        return $searchResult->first();
    }

    /* Depending on the flag $applyFilter all orders will be returned if false, a filtration by date will happen if true*/
    public function getOrderEntities(bool $applyFilter, Context $context)
    {
        $orderRepository = $this->container->get('order.repository');
        $criteria = $applyFilter ? $this->addCriteriaFilterDate(new Criteria()) : new Criteria();

        /** @var EntitySearchResult $entities */
        $entities = $orderRepository->search($criteria, $context);

        if(count($entities) === 0){
            return 0;
        }
        return $entities;
    }
    
    /* Returns the order entity depending on the datafield identifier */
    public function getOrder($orderRepository, $identifier, $filenameContents, $context)
    {
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($identifier, $filenameContents));
        /** @var EntitySearchResult $entities */
        $orderEntities = $orderRepository->search($criteria, $context);
        /** @var OrderEntity $order */
        $order = $orderEntities->first();
        return $order;
    }

    /* Returns an array with all saved order line items associated to the given orderID */
    public function getOrderedProducts(string $orderID, Context $context): array
    {
        $lineItemsRepository = $this->container->get('order_line_item.repository');
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderID));
        /** @var EntitySearchResult $lineItemEntity */
        $lineItemEntity = $lineItemsRepository->search($criteria, $context);

        $lineItemArray = [];
        $i = 0;
        foreach($lineItemEntity as $lineItem)
        {
            $lineItemArray[$i] = $lineItem;
            $i++;
        }

        return $lineItemArray;
    }

    /* Returns the billing as well as the shipping address in a single array, if the 2nd half is empty the first 6 entries are used as billing AND shipping address */
    public function getDeliveryAddress(string $orderID, string $eMailAddress, Context $context): array
    {
        $orderDeliveryAddressRepository = $this->container->get('order_address.repository');
        /** @var Criteria $criteria */
        $criteria = new Criteria(); //create criteria
        $criteria->addFilter(new EqualsFilter('orderId', $orderID)); //add filter
        /** @var EntitySearchResult $addressEntity */
        $addressEntity = $orderDeliveryAddressRepository->search($criteria, $context); // search the repository with the predefined criteria argument

        /** @var OrderAddressEntity $deliverAddressEntity */
        $deliverAddressEntity;
        /** @var OrderAddressEntity $customerAddressEntity */
        $customerAddressEntity;
        if (count($addressEntity) === 1) // check if there are multiple addressEntities saved for this order
        {
            $customerAddressEntity = $addressEntity->first();
            $deliverAddressEntity = $addressEntity->first();
        }
        else
        {// if array is 2 long, the first entry is customer, 2nd entry is delivery address
            $customerAddressEntity = $addressEntity->first();
            $deliverAddressEntity = $addressEntity->last();
        }

        return $addressArray = array(
            'eMail' => $eMailAddress,
            'firstNameCustomer' => $customerAddressEntity->getFirstName(),
            'lastNameCustomer' => $customerAddressEntity->getLastName(),
            'zipCodeCustomer' => $customerAddressEntity->getZipcode(),
            'cityCustomer' => $customerAddressEntity->getCity(),
            'streetCustomer' => $customerAddressEntity->getStreet(),
            'countryISOalpha2Customer' => $this->getCountryISOalpha2($customerAddressEntity->getCountryId()),
            'firstNameDelivery' => $deliverAddressEntity->getFirstName(),
            'lastNameDelivery' => $deliverAddressEntity->getLastName(),
            'zipCodeDelivery' => $deliverAddressEntity->getZipcode(),
            'cityDelivery' => $deliverAddressEntity->getCity(),
            'streetDelivery' => $deliverAddressEntity->getStreet(),
            'countryISOalpha2Delivery' => $this->getCountryISOalpha2($deliverAddressEntity->getCountryId())
        );
    }

    /* Returns the ISO Alpha value of countryID */
    private function getCountryISOalpha2(string $countryID):string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $countryID));
        /** @var CountryEntity $countryEntity */
        $countryEntity = $this->container->get('country.repository')->search($criteria,Context::createDefaultContext())->first();
        return $countryEntity->getIso();
    }

    /* Returns the entity ID of the delivery DB entry according to the given orderID */
    public function getDeliveryEntityID($orderDeliverRepository, string $orderEntityID, Context $context): string
    {
        $criteria = new Criteria();
        $entities = $orderDeliverRepository->search($criteria, $context);

        /** @var OrderDeliveryEntity $orderDelivery */
        foreach($entities as $entityID => $orderDelivery)
        {
            if ($orderDelivery->getOrderId() === $orderEntityID)
            {
                return $orderDelivery->getId();
            }
        }
    }

    /* Adds tracking numbers to orderDelivery DB entry */
    public function updateTrackingNumbers($orderDeliveryRepository, $orderDeliveryID, $trackingnumbers, $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter("id",$orderDeliveryID));

        /** @var EntitySearchResult $searchResult */
        $searchResult = $orderDeliveryRepository->search($criteria, $context);
        /** @var OrderDeliveryEntity $orderDeliveryEntity */
        $orderDeliveryEntity = $searchResult->first();

        $currentTrackingnumbers = $orderDeliveryEntity->getTrackingCodes();

        foreach($trackingnumbers as $value){
            if(!in_array($value, $currentTrackingnumbers, true)){
                array_push($currentTrackingnumbers, $value);
            }
        }
        $orderDeliveryRepository->update([
                                             [ 'id' => $orderDeliveryID, 'trackingCodes' => $currentTrackingnumbers ],
                                         ],
                                         $context);

    }

    // Checks if cancellation of the passed order has already been confirmed, returns true if entry already exists
    public function OrderCancelConfirmationExistsCk(EntityRepositoryInterface $cancelConfirmationRepository, string $orderID,Context $context): bool
    {
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('orderId', $orderID));

        /** @var EntitySearchResult $searchResult */
        $searchResult = $cancelConfirmationRepository->search($criteria, $context);
        
        return count($searchResult) != 0 ? true : false;
    }

    
}