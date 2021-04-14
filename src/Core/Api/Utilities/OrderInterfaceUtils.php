<?php declare(strict_types=1);

/*

OrderInterfaceUtils holds most helper functions for the order interface controller.

*/

namespace ASOrderInterface\Core\Api\Utilities;

use ASDispositionControl\Core\Content\DispoControlData\DispoControlDataEntity;
use ASMailService\Core\MailServiceHelper;
use Psr\Container\ContainerInterface;
use DateInterval;
use DateTime;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use ASOrderInterface\Core\Content\StockQS\OrderInterfaceStockQSEntity;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class OrderInterfaceUtils
{
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    /** @var MailServiceHelper $mailServiceHelper */
    private $mailServiceHelper;
    /** @var string $folderRoot */
    private $folderRoot;
    /** @var string $folderRoot */
    private $senderName;
    /** @var ContainerInterface $container */
    protected $container;
    
    public function __construct(SystemConfigService $systemConfigService,
                                MailServiceHelper $mailServiceHelper)
    {
        $this->systemConfigService = $systemConfigService;
        $this->mailServiceHelper = $mailServiceHelper;
        $this->senderName = 'Order Interface';
        $this->folderRoot = $this->systemConfigService->get('ASOrderInterface.config.workingDirectory');
    }
    

    /** @internal @required */
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $previous = $this->container;
        $this->container = $container;

        return $previous;
    }

    public function generateFolderStructure()
    {
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive', 0777, true);
        }
        
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/Articlebase')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/Articlebase', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/SubmittedOrders')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/SubmittedOrders', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/Artikel_Error')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/Artikel_Error', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/Artikel_Error')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/Artikel_Error', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/RM_WA')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/RM_WA', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/RM_WA')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/RM_WA', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/RM_WE')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/RM_WE', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/RM_WE')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/RM_WE', 0777, true);
        }

        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/Bestand')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/ReceivedStatusReply/Bestand', 0777, true);
        }
        if (!file_exists('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/Bestand')) {
            mkdir('../custom/plugins/ASOrderInterface/InterfaceData/Archive/ReceivedStatusReply/Bestand', 0777, true);
        }
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

    /* Returns an array with all saved order line items associated to the given orderID */
    public function getOrderedProducts(string $orderID, Context $context): array
    {
        /** @var EntitySearchResult $lineItemEntity */
        $lineItemEntity = $this->getFilteredEntitiesOfRepository($this->container->get('order_line_item.repository'),'orderId',$orderID,$context);
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
        /** @var EntitySearchResult $addressEntity */
        $addressEntity = $this->getFilteredEntitiesOfRepository($this->container->get('order_address.repository'),'orderId',$orderID,$context);

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

    /* Updates stock according to logistics partner response */
    public function updateProduct(string $productNumber, $availableStockAddition, $context)
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');

        /** @var ProductEntity $productEntity */
        $productEntity = $this->getFilteredEntitiesOfRepository($productRepository, 'productNumber', $productNumber, $context)->first();
        if($productEntity == null)
            return;
        $currentStock = $productEntity->getStock();
        $newStockValue = $currentStock + intval($availableStockAddition);

        $productRepository->update(
            [
                [ 'id' => $productEntity->getId(), 'stock' => $newStockValue ],
            ],
            $context
        );
    }    

    /* */
    public function updateQSStock(array $lineContents, string $productNumber, Context $context)
    {
        /** @var EntityRepositoryInterface $stockQSRepository */
        $stockQSRepository = $this->container->get('as_stock_qs.repository');
        /** @var ProductEntity $product */
        $product = $this->getFilteredEntitiesOfRepository($this->container->get('product.repository'), 'productNumber', $productNumber, $context)->first();
        if($product == null)
            return;

        /** @var EntitySearchResult $searchResult */
        $searchResult = $this->getFilteredEntitiesOfRepository($stockQSRepository, 'productId', $product->getId(), $context);
            
        if(count($searchResult) == 0)
        {
            // generate new entry
            $stockQSRepository->create([
                ['productId' => $product->getId(), 
                'faulty' => intval($lineContents[8]), 
                'clarification' => intval($lineContents[9]), 
                'postprocessing' => intval($lineContents[10]), 
                'other' => intval($lineContents[11])],
            ],
                $context
            );
            return;
        }
        else
        {
            // update entry
            $entry = $searchResult->first();

            /** @var OrderInterfaceStockQSEntity $stockQSEntity */
            $stockQSEntity = $searchResult->first();
            $faulty = $stockQSEntity->getFaulty();
            $clarification = $stockQSEntity->getClarification();
            $postprocessing = $stockQSEntity->getPostprocessing();
            $other = $stockQSEntity->getOther();
            
            $stockQSRepository->update([
                ['id' => $entry->getId(), 
                'productId' => $product->getId(), 
                'faulty' => intval($lineContents[8]) + $faulty, 
                'clarification' => intval($lineContents[9]) + $clarification, 
                'postprocessing' => intval($lineContents[10]) + $postprocessing, 
                'other' => intval($lineContents[11]) + $other],
            ],
                $context
            );
        }
    }

    public function updateDispoControlData(string $productNumber, $amount, $context)
    {
        /** @var EntityRepositoryInterface $asDispoDataRepository */
        $asDispoDataRepository = $this->container->get('as_dispo_control_data.repository');
        /** @var DispoControlDataEntity $entity*/
        $entity = $this->getFilteredEntitiesOfRepository($asDispoDataRepository, 'productNumber', $productNumber, $context)->first();

        if(count($entity) == 0 || $amount == 0)
            return;

        $asDispoDataRepository->update([
            ['id' => $entity->getId(), 
            'incoming' => $entity->getIncoming()-$amount],
        ], $context);
    } 

    public function updateQSStockBS(int $faulty, int $postprocessing, int $other, int $clarification, ProductEntity $productEntity, Context $context)
    {
        /** @var EntityRepositoryInterface $stockQSRepository */
        $stockQSRepository = $this->container->get('as_stock_qs.repository');

        $productID = $productEntity->getId();

        /** @var EntitySearchResult $searchResult */
        $searchResult = $this->getFilteredEntitiesOfRepository($stockQSRepository, 'productId', $productID, $context);

        if(count($searchResult) == 0)
        {
            // generate new entry
            $stockQSRepository->create([
                ['productId' => $productID, 
                'faulty' => $faulty, 
                'clarification' => $clarification, 
                'postprocessing' => $postprocessing, 
                'other' => $other],
            ], $context);
            return;
        }
        else
        {
            /** @var OrderInterfaceStockQSEntity $stockQSEntity */
            $stockQSEntity = $searchResult->first();
            $currentFaulty = $stockQSEntity->getFaulty();
            $currentClarification = $stockQSEntity->getClarification();
            $currentPostprocessing = $stockQSEntity->getPostprocessing();
            $currentOther = $stockQSEntity->getOther();
            
            $stockQSRepository->update([
                ['id' => $stockQSEntity->getId(), 
                'productId' => $productID, 
                'faulty' => $currentFaulty + $faulty, 
                'clarification' => $currentClarification + $clarification, 
                'postprocessing' => $currentPostprocessing + $postprocessing, 
                'other' => $currentOther + $other],
            ],
                $context
            );
        }
    }

    public function processQSK(string $productNumber, int $faulty, int $clarification, int $postprocessing, int $other, int $stock ,Context $context)
    {
        /** @var EntityRepositoryInterface $stockQSRepository */
        $stockQSRepository = $this->container->get('as_stock_qs.repository');
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');
        /** @var ProductEntity $product */
        $product = $this->getFilteredEntitiesOfRepository($productRepository, 'productNumber', $productNumber, $context)->first();
        /** @var OrderInterfaceStockQSEntity $stockQSEntity */
        $stockQSEntity = $this->getFilteredEntitiesOfRepository($stockQSRepository, 'productId', $product->getId(), $context)->first();

        if($stockQSEntity == null)
        {
            if($faulty < 0 || $clarification < 0 || $postprocessing < 0 || $other < 0)
            {
                $this->sendErrorNotification('QSK error','major error, check logs.<br>A new stock qs entry tried to be created with negative values.', [''], false);
                return;
            }
            $stockQSRepository->create([
                ['productId' => $product->getId(), 
                'faulty' => $faulty, 
                'clarification' => $clarification, 
                'postprocessing' => $postprocessing, 
                'other' => $other],
            ], $context);
        }
        else
        {
            $currentFaulty = $stockQSEntity->getFaulty();
            $currentClarification = $stockQSEntity->getClarification();
            $currentPostprocessing = $stockQSEntity->getPostprocessing();
            $currentOther = $stockQSEntity->getOther();

            $stockQSRepository->update([
                ['id' => $stockQSEntity->getId(), 
                'faulty' => $currentFaulty + $faulty, 
                'clarification' => $currentClarification + $clarification, 
                'postprocessing' => $currentPostprocessing + $postprocessing, 
                'other' => $currentOther + $other],
            ], $context);

            

            $currentStock = $product->getStock();
            $newStockValue = $currentStock + $stock;
            $productRepository->update(
                [
                    [ 'id' => $product->getId(), 
                    'stock' => $newStockValue ],
                ],
                $context
            );
            if($newStockValue < 0)
            {
                $this->sendErrorNotification('QSK error','New stockvalue is below 0, check logs and data' . $product->getProductNumber(),[''], false);
            }
        }
    }

    /* Sends an eMail to every entry in the plugin configuration inside the administration frontend */
    public function sendErrorNotification(string $errorSubject, string $errorMessage, array $fileArray, bool $critical)
    {
        $notificationSalesChannel = $this->systemConfigService->get('ASOrderInterface.config.fallbackSaleschannelNotification');

        if($critical){
            $recipientList = $this->systemConfigService->get('ASOrderInterface.config.systemErrorNotificationRecipients');
        }else{
            $recipientList = $this->systemConfigService->get('ASOrderInterface.config.errorNotificationRecipients');
        }
        
        $recipientData = explode(';', $recipientList);
        $recipients = null;
        for ($i = 0; $i< count($recipientData); $i +=2 )
        {
            $recipientName = $recipientData[$i];
            $recipientAddress = $recipientData[$i+1];

            $mailCheck = explode('@', $recipientAddress);
            if(count($mailCheck) != 2)
            {
                continue;
            }
            $recipients[$recipientAddress] = $recipientName;
        }

        $this->mailServiceHelper->sendMyMail($recipients, $notificationSalesChannel, $this->senderName, $errorSubject, $errorMessage, $errorMessage, $fileArray);
    }

    public function isMyScheduledTaskCk(string $taskName): bool
    {
        if($taskName == 'as.scheduled_order_transfer_task')
            return true;
        if($taskName == 'as.scheduled_order_process_article_error')
            return true;
        if($taskName == 'as.scheduled_order_process_rmwa')
            return true;
        if($taskName == 'as.scheduled_order_process_rmwe')
            return true;
        if($taskName == 'as.scheduled_order_process_stock_feedback')
            return true;
    
        return false;
    }

    /* Deletes recursive every file and folder in given path. So... be careful which path gets passed to this function */
    public function deleteFiles($dir)
    {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it,
                RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) 
        {
            if ($file->isDir())
            {
                rmdir($file->getRealPath());
            }
            else 
            {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
    /* Deletes recursive every file and folder in given path. So... be careful which path gets passed to this function */
    public function archiveFiles($dir,$delete,string $from)
    {
        if(!$delete)
        {
            $archivePath = $this->folderRoot . "Archive/${from}";
            $files = scandir($dir);
            if ($files != 0)
            {
                $this->createTodaysFolderPath($archivePath,$timeStamp);
                $archivePath = $archivePath . $timeStamp . '/'; 
                if (!file_exists($archivePath)) {
                    mkdir($archivePath, 0777, true);
                }
                for($i = 2; $i < count($files); $i++)
                {
                    $source = $dir . $files[$i]; 
                    $dest = $archivePath . $files[$i]; 
                    // $this->sendErrorNotification("Archive Files from ${from}","Copying from: ${source}<br>To:${dest}",['']);
                    copy($source,$dest);
                }
            }            
        }
        // $this->sendErrorNotification("Archive Files from ${from}","Deleting: ${dir}",['']);
        $this->deleteFiles($dir);   
        $this->tidyUpArchive($this->folderRoot . 'Archive');  
    }

    public function getAllEntitiesOfRepository(EntityRepositoryInterface $repository, Context $context): ?EntitySearchResult
    {   
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        /** @var EntitySearchResult $result */
        $result = $repository->search($criteria,$context);

        return $result;
    }

    public function getFilteredEntitiesOfRepository(EntityRepositoryInterface $repository, string $fieldName, $fieldValue, Context $context): ?EntitySearchResult
    {   
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($fieldName, $fieldValue));
        /** @var EntitySearchResult $result */
        $result = $repository->search($criteria,$context);

        return $result;
    }

    public function entityExistsInRepositoryCk(EntityRepositoryInterface $repository, string $fieldName, $fieldValue, Context $context): bool
    {
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter($fieldName, $fieldValue));

        /** @var EntitySearchResult $searchResult */
        $searchResult = $repository->search($criteria, $context);
        
        return count($searchResult) != 0 ? true : false;
    }
    public function tidyUpArchive($path)
    {
        $this->deleteEmptyFolders($path . '/SubmittedOrders');
        $this->deleteEmptyFolders($path . '/Articlebase');
        $this->deleteEmptyFolders($path . '/ReceivedStatusReply//RM_WE');
        $this->deleteEmptyFolders($path . '/ReceivedStatusReply//RM_WA');
        $this->deleteEmptyFolders($path . '/ReceivedStatusReply//Bestand');
        $this->deleteEmptyFolders($path . '/ReceivedStatusReply//Artikel_Error');
    }
    private function deleteEmptyFolders($path)
    {
        $file = '';
        $remove = false;
        $empty=true;
        foreach (glob($path.DIRECTORY_SEPARATOR."*") as $file) {
            $empty &= is_dir($file) && $this->deleteEmptyFolders($file);
        }
        if($empty)
            $remove = true;
        if($this->isBaseFolder($file))
            $remove = false;
        if($remove)
            rmdir($path);

        return $remove;
    }
    private function isBaseFolder($file): bool
    {
        if($file = '')
            return false;

        $fileExploded = explode('/', $file);
        $fileName = $fileExploded[count($fileExploded)-1];

        if(
            $fileName == 'SubmittedOrders' ||
            $fileName == 'Articlebase' ||
            $fileName == 'ReceivedStatusReply' ||
            $fileName == 'RM_WE' ||
            $fileName == 'RM_WA' ||
            $fileName == 'Bestand' ||
            $fileName == 'Artikel_Error'
        )
        {
            return true;
        }
        return false;
    }
}