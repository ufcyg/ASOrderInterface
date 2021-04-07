<?php declare(strict_types=1);

namespace ASOrderInterface\Core\Api;

use ASControllingReport\Core\Api\ASControllingReportController;
use ASDispositionControl\Core\Api\ASDispoControlController;
use ASDispositionControl\Core\Content\DispoControlData\DispoControlDataEntity;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use ASOrderInterface\Core\Api\Utilities\CSVFactory;
use ASOrderInterface\Core\Api\Utilities\SFTPController;
use ASOrderInterface\Core\Api\Utilities\OIOrderServiceUtils;
use ASOrderInterface\Core\Api\Utilities\OrderInterfaceUtils;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use ASOrderInterface\Core\Content\StockQS\OrderInterfaceStockQSEntity;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderInterfaceController extends AbstractController
{
    /** @var SFTPController $sftpController */
    private $sftpController;
    /** @var CSVFactory $csvFactory */
    private $csvFactory;
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    /** @var OrderInterfaceUtils $oiUtils */
    private $oiUtils;
    /** @var string $companyID */
    private $companyID;
    /** @var OIOrderServiceUtils $oiOrderServiceUtils */
    private $oiOrderServiceUtils;
    /** @var string $senderName */
    private $senderName;
    /** @var ASControllingReportController $controllingReportController */
    private $controllingReportController;
    /** @var ASDispoControlController $dispoController */
    private $dispoController;
    /** @var string $workingDirectory */
    private $workingDirectory;

    public function __construct(SystemConfigService $systemConfigService,
                                CSVFactory $csvFactory,
                                OrderInterfaceUtils $oiUtils,
                                OIOrderServiceUtils $oiOrderServiceUtils,
                                ASControllingReportController $controllingReportController,
                                ASDispoControlController $dispoController,
                                SFTPController $sftpController)
    {
        $this->systemConfigService = $systemConfigService;
        $this->csvFactory = $csvFactory;
        $this->oiUtils = $oiUtils;
        $this->companyID = $this->systemConfigService->get('ASOrderInterface.config.logisticsCustomerID');
        $this->oiOrderServiceUtils = $oiOrderServiceUtils;
        $this->senderName = 'Order Interface';

        $this->controllingReportController = $controllingReportController;
        $this->dispoController = $dispoController;

        $this->workingDirectory = $this->systemConfigService->get('ASOrderInterface.config.workingDirectory');

        $this->sftpController = $sftpController;
    }

    /* Transmission of local file to destination path on remote sFTP server */
    private function sendFile(string $filePath, string $destinationPath)
    {
        $this->sftpController->pushFile($filePath, $destinationPath);
    }

    /**
     * @Route("/api/v{version}/_action/as-order-interface/dummyRoute", name="api.custom.as_order_interface.dummyRoute", methods={"POST"})
     * @param Context $context;
     * @return Response
     * just my debug function called by insomnia, change what you want, it isn't used anywhere
     */
    public function dummyRoute(Context $context)
    {

        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/as-order-interface/processAnswers", name="api.custom.as_order_interface.processAnswers", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Checks for answers by logistics partner.
     */
    public function processAnswers(Context $context): ?Response
    {
        $response = $this->pullArticleError($context);
        $response = $this->pullRMWE($context);
        $response = $this->pullRMWA($context);
        $response = $this->pullStock($context);
        return $response;
    }

    /**
     * @Route("/api/v{version}/_action/as-order-interface/submitArticlebase", name="api.custom.as_order_interface.submitArticlebase", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Writes file with full articlebase local and transmits the file to the designated sFTP server.
     */
    public function submitArticlebase(Context $context): ?Response
    { 
        /** @var EntitySearchResult $products */
        $products = $this->oiUtils->getAllEntitiesOfRepository($this->container->get('product.repository'), $context);

        $csvString = '';
        foreach ($products as $product)
        {
            $csvString = $this->csvFactory->generateArticlebase($csvString, $product, $context);
        }
        $articlebasePath = $this->oiUtils->createTodaysFolderPath('Archive/Articlebase', $timeStamp);

        if (!file_exists($articlebasePath)) {
            mkdir($articlebasePath, 0777, true);
        }   
        $filename = $articlebasePath . '/' . $this->companyID . '.' . 'Artikelstamm-' . $timeStamp . '.csv';
        file_put_contents($filename, $csvString);

        
        $this->sendFile($filename, "/Artikel" . "/artikelstamm" . $this->oiUtils->createShortDateFromString('now') . ".csv");
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/as-order-interface/submitOrders", name="api.custom.as_order_interface.submitOrders", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Checks for open orders and transmits them to the designated sFTP server
     */
    public function submitOrders(Context $context): ?Response
    {
        /** @var EntitySearchResult $orders */
        $orders = $this->oiUtils->getAllEntitiesOfRepository($this->container->get('order.repository'), $context);
        if($orders == null){
            return new Response('',Response::HTTP_NO_CONTENT);
        }
        if(count($orders) === 0){
            return new Response('',Response::HTTP_NO_CONTENT);
        }
        /** @var OrderEntity $order */
        foreach($orders as $orderID => $order)
        {
            if(strcmp($order->getStateMachineState()->getTechnicalName(),'open') == 0)
            {
                $fileContent = $this->generateOrderContent($order, false, $context);
            }
            else if (strcmp($order->getStateMachineState()->getTechnicalName(),'cancelled') == 0)
            {
                // get repository for confirmed cancelled orders
                $cancelledConfirmationRepository = $this->container->get('as_cancelled_confirmation.repository');
                //check if order has already been confirmed, if it isn't existant create new entity
                if($this->oiUtils->entityExistsInRepositoryCk($cancelledConfirmationRepository, 'orderId', $order->getId(), $context))
                {
                    continue;
                }
                $fileContent = $this->generateOrderContent($order, true, $context);
            }
            $folderPath = $this->oiUtils->createTodaysFolderPath('Archive/SubmittedOrders', $timeStamp);
            $filePath = $this->oiUtils->writeOrder($order->getOrderNumber(), $folderPath, $fileContent, $this->companyID);
            
            $this->sendFile($filePath, "/WA" . "/waavis" . $order->getOrderNumber() . ".csv");
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /* Extracts all necessary data of the order for the logistics partner throught the CSVFactory */
    public function generateOrderContent(OrderEntity $order, bool $orderCancelled, $context): string
    {
        // init exportData variable, this will contain the billing/delivery address aswell as every line item of the order
        $exportData = [];
        /** @var string $orderID */
        $orderID = $order->getId(); // orderID used to search inside other Repositories for corresponding data
        /** @var OrderCustomerEntity $orderCustomerEntity */
        $orderCustomerEntity = $order->getOrderCustomer();
        // deliveryaddress 
        $exportData = $this->oiUtils->getDeliveryAddress($orderID, $orderCustomerEntity->getEmail(), $context);
        $orderedProducts = $this->oiUtils->getOrderedProducts($orderID, $context);
        
        $i = 0;
        /** @var OrderLineItemEntity $orderLineItem */
        foreach($orderedProducts as $orderLineItem)
        {//iterate through all products contained in this order
            if ($orderLineItem->getIdentifier() === "INTERNAL_DISCOUNT")
            {//ignore the internal discount added if the ordering customer is an internal customer to avoid errors due to missing articlenumber etc.
                continue;
            }
            array_push($exportData, $orderLineItem); // adding the lineitems to $exportData variable
            $orderContent = $this->csvFactory->generateDetails($exportData, $order->getOrderNumber(), $i, '', $orderCancelled, $context); 
            $i++;
        }
        $fileContent = $this->csvFactory->generateHeader($exportData, $order->getOrderNumber(), '', $orderCustomerEntity->getCustomerId(), $context);
        $fileContent = $fileContent . $orderContent; // concatenation of header, which has to be written after the contents, due to the order value being calculated after every line item has been processed
        return $fileContent;
    }

    /**
     * @Route("/api/v{version}/_action/as-order-interface/pullRMWA", name="api.custom.as_order_interface.pullRMWA", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Pulls feedback about goods dispatchment from logistics partner
     */
    public function pullRMWA(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WA', $timeStamp);

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'RM_WA');
        return $this->checkRMWA($context);
    }
    /**
     * @Route("/api/v{version}/_action/as-order-interface/checkRMWA", name="api.custom.as_order_interface.checkRMWA", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Processes pulled feedback about goods dispatchment from logistics partner
     */
    public function checkRMWA(Context $context): ?Response
    {
        $deleteFilesWhenFinished = false; // since a false bool value is null for shopware we predefine the false value... 
        //get flag for deleting files when finished
        $deleteFilesWhenFinished = $this->systemConfigService->get('ASOrderInterface.config.deleteFilesAfterEvaluation');
        //create path for destination of transferred files
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WA', $timeStamp) . '/';
        if (file_exists($path)) { // prevent exception if the folder couldn't be created due to missing rights
            $files = scandir($path); //get all files / folders 
            for($i = 2; $i < count($files); $i++) //iterate through every file in the folder
            {
                $filename = $files[$i]; // get the filename of current file
                $filenameContents = explode('_',$filename); // get the parts of the filename separated by a delimiter, in this case ('_')

                if($filenameContents[1] === 'STATUS') // status of order data procession
                {
                    /** @var OrderEntity $order */
                    $order = $this->oiUtils->getFilteredEntitiesOfRepository($this->container->get('order.repository'), 'orderNumber', $filenameContents[3],$context)->first();

                    if($order == null)
                    {// wrong kind of file has been read, notify administration and prevent deletion of files
                        $deleteFilesWhenFinished = false;
                        $this->oiUtils->sendErrorNotification('MAJOR ERROR','Major error occured.<br>Received reply for non existant order. Received filename:<br>' . $filename, [$path . $filename]);
                        continue;
                    }
                    switch ($filenameContents[2])
                    {
                        case '003': // order cannot be changed (already packed, shipped, cancelled)
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WA Status 003','Status 003 "order cannot be changed (already packed, shipped, cancelled)" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '005': // cannot be cancelled because never created
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WA Status 005','Status 005 "cannot be cancelled because already processed or cancelled" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '006': // cannot be cancelled because already processed or cancelled
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WA Status 006','Status 006 "cannot be cancelled because already processed or cancelled" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '007': // order changed
                            $this->oiUtils->sendErrorNotification('RM_WA Status 007','Status 007 "order successfully changed" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '009': // minor error in order
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WA Status 009','Status 009 "minor error in order" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '010': // order sucessfully imported to rieck LFS
                            $result = $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'process');
                        break;
                        case '040': // order packaging started, order cannot be changed anymore
                            $result = $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'complete');
                        break;
                        case '999': // major error (file doesn't meet the expectations, e.g. unfitting fieldlengths, fieldformats, missing necessary fields)
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('MAJOR ERROR','Major error occured.<br>One or more submitted files did not meet expectations and been rejected.', [$path . $filename]);
                        break;
                        default:
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('MAJOR ERROR','Major error occured.<br>File could not be recognized.', [$path . $filename]);
                        break;
                    }
                    continue;
                }
                else if ($filenameContents[1] === 'WAK') // Packende / end of packing
                {
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);
                    $headContents = explode(';',$fileContentsByLine[0]);
                }
                else if ($filenameContents[1] === 'STORNO') // cancellation(confirmation) by rieck
                {
                    /** @var OrderEntity $order */
                    $order = $this->oiUtils->getFilteredEntitiesOfRepository($this->container->get('order.repository'), 'orderNumber', $filenameContents[2],$context)->first();
                    
                    // get repository for confirmed cancelled orders
                    $cancelledConfirmation = $this->container->get('as_cancelled_confirmation.repository');
                    //check if order has already been confirmed, if it isn't existant create new entity
                    if(!$this->oiUtils->entityExistsInRepositoryCk($cancelledConfirmation, 'orderId', $order->getId(), $context))
                    {
                        $cancelledConfirmation->create([
                            ['orderId' => $order->getId()],
                        ],
                        $context);
                    }
                    $result = $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'cancel');
                    if($result)
                    {
                        $this->oiUtils->sendErrorNotification('Order cancelled by logistics partner','Order<br>' . $order->getOrderNumber() . '<br>has been cancelled by logistics partner.<br>Communication needed.', [$path . $filename]);
                    }
                }
                else if ($filenameContents[1] === 'VLE') // packages loaded, we will have the tracking numbers and add them to the orderdelivery repository datafield
                {
                    /** @var EntityRepositoryInterface $orderDeliveryRepository */
                    $orderDeliveryRepository = $this->container->get('order_delivery.repository');
                    /** @var EntityRepositoryInterface $orderLineItemRepository */
                    $orderLineItemRepository = $this->container->get('order_line_item.repository');
                    /** @var OrderEntity $order */
                    $order = $this->oiUtils->getFilteredEntitiesOfRepository($this->container->get('order.repository'), 'orderNumber', $filenameContents[2],$context)->first();
                    if ($order == null)
                        continue;
                    /** @var OrderDeliveryEntity $orderDeliveryEntity */
                    $orderDelivery = $this->oiUtils->getFilteredEntitiesOfRepository($this->container->get('order_delivery.repository'),'orderId', $order->getId(), $context)->first();                    
                    
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);

                    $condensedArray = array();      
                    $prevValue = 0;
                    for($x = 1; $x < count($fileContentsByLine); $x++)
                    {
                        $contentLine = explode(';', $fileContentsByLine[$x]);

                        if(count($contentLine) <= 1) // skip if line contains no information
                            continue;
                            
                        $this->controllingReportController->generateControllingEntityFromVLE($contentLine);

                        // $contentLineFields = explode(';',$contentLine);
                        $productNumber = $contentLine[5];
                        if($productNumber == "99999") // skip if line contains information about stored files, they always have the product ID 99999
                            continue;

                        $prevValue = array_key_exists($productNumber, $condensedArray) ? $condensedArray[$productNumber] : 0;
                        $condensedArray[$productNumber] = $prevValue + intval($contentLine[6]);
                    }

                    foreach($condensedArray as $productNumber => $reportedAmount)
                    {
                        $orderID = $order->getId();
                        $orderedProducts = $this->oiUtils->getOrderedProducts($orderID, $context);

                        /** @var OrderLineItemEntity $orderLineItem */
                        foreach($orderedProducts as $orderLineItem)
                        {
                            /** @var ProductEntity $productToCompare */
                            $productToCompare = $this->oiUtils->getFilteredEntitiesOfRepository($this->container->get('product.repository'), 'productNumber', $productNumber, $context)->first();
                            if($productToCompare->getId() == $orderLineItem->getProductId())
                            {
                                if($orderLineItem->getQuantity() != $reportedAmount)
                                {
                                    $deleteFilesWhenFinished = false;
                                    if($reportedAmount == 0)
                                    {
                                        $orderLineItemRepository->delete([
                                            ['id' => $orderLineItem->getId()],
                                        ],$context);
                                    }
                                    else
                                    {
                                        $unitPrice = $orderLineItem->getUnitPrice();
                                        $totalPrice = $unitPrice * $reportedAmount;
                                        /** @var QuantityPriceDefinition $orderLineItemPriceDefinition */
                                        $orderLineItemPriceDefinition = $orderLineItem->getPriceDefinition();
                                        $orderLineItemPriceDefinition->setQuantity($reportedAmount);

                                        /** @var Price $orderLineItemPrice */
                                        $orderLineItemPrice = $orderLineItem->getPrice();
                                        $orderLineItemRepository->update(
                                            [
                                                [ 'id' => $orderLineItem->getId(), 'quantity' => $reportedAmount],
                                            ],
                                            Context::createDefaultContext()
                                        );
                                    }
                                    $this->oiUtils->sendErrorNotification('Order deviation VLE', 'Rieck was not able to pack enough product.<br>EntryID: ' . $x, [$path . $filename]);
                                }
                            }
                        }
                    }

                    $headContents = explode(';',$fileContentsByLine[0]);
                    $trackingnumbers = array();
                    for ($j = 1; $j < count($fileContentsByLine)-1; $j++)
                    {
                        $lineContents = explode(';', $fileContentsByLine[$j]);
                        array_push($trackingnumbers,$lineContents[9]);
                    }
                    $stateChanged = $this->oiOrderServiceUtils->updateOrderDeliveryStatus($orderDelivery, $orderDelivery->getId(), 'ship');
                    if($stateChanged) // unly update tracking numbers if the parcel hasn't been shipped yet
                    {
                        $this->oiUtils->updateTrackingNumbers($orderDeliveryRepository, $orderDelivery->getId(), array_unique($trackingnumbers), $context);
                    }
                }
            }
        } 
        $this->oiUtils->archiveFiles($path,$deleteFilesWhenFinished,'ReceivedStatusReply/RM_WA/');
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    
    /**
     * @Route("/api/v{version}/_action/as-order-interface/pullRMWE", name="api.custom.as_order_interface.pullRMWE", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Pulls feedback about goods receipt from logistics partner
     */
    public function pullRMWE(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WE', $timeStamp);

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'RM_WE');
        return $this->checkRMWE($context);
    }
    /**
     * @Route("/api/v{version}/_action/as-order-interface/checkRMWE", name="api.custom.as_order_interface.checkRMWE", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Processes pulled feedback about goods receipt from logistics partner
     */
    public function checkRMWE(Context $context): ?Response
    {
        $deleteFilesWhenFinished = false; // since a false bool value is null for shopware we predefine the false value... 
        //get flag for deleting files when finished
        $deleteFilesWhenFinished = $this->systemConfigService->get('ASOrderInterface.config.deleteFilesAfterEvaluation');

        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WE', $timeStamp) . '/'; // get path of pulled files
        if (file_exists($path)) { // prevent exception if the folder couldn't be created due to missing rights
            $files = scandir($path); //get all files / folders 
            for ($i = 2; $i < count($files); $i++) { // iterate through every file in the folder
                $filename = $files[$i]; 
                $filenameContents = explode('_',$filename);

                if($filenameContents[1] == 'STATUS')
                {
                    switch($filenameContents[2])
                    {
                        case '001': //WEAvis couldn't be created
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WE Status 001','Status 001 "WEAvis could not be created" for order<br>' . $filenameContents[3], [$path . $filename]);
                        break;
                        case '005': //WEAvis cannot be cancelled due to being not existant, already processed or cancelled
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WE Status 005','Status 005 "WEAvis cannot be cancelled due to being not existant, already processed or cancelled" for order<br>' . $filenameContents[3], [$path . $filename]);
                        break;
                        case '007': //WEAvis change processed
                            // $this->sendErrorNotification('RM_WE Status 007','Status 007 "WEAvis could not be created" for order ' . $filenameContents[3]);
                        break;
                        case '009': //WEAvis could not be processed, errors inside WEAvis
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WE Status 009','Status 009 "WEAvis could not be processed, errors inside WEAvis" for order<br>' . $filenameContents[3], [$path . $filename]);
                        break;
                        case '010': //WEAvis processed
                            // $this->sendErrorNotification('RM_WE Status 010','Status 010 "WEAvis could not be created" for order ' . $filenameContents[3]);
                        break;
                        case '999': //WEAvis message could not be processed, out of specification
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WE Status 999','Status 999 "WEAvis message could not be processed, out of specification" for order<br>' . $filenameContents[3], [$path . $filename]);
                        break;
                        default:
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WE Status unknown','unkown status reported', [$path . $filename]);
                        break;
                    }
                }
                else if($filenameContents[1] == 'ERROR')
                {
                    $this->oiUtils->sendErrorNotification('RM_WE Status ERROR','Unknown Error reported', [$path . $filename]);
                }
                else if($filenameContents[1] == 'WKE')
                {
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);
                    $headContents = explode(';',$fileContentsByLine[0]);
                    for ($j = 1; $j < count($fileContentsByLine); $j++)
                    {
                        $lineContents = explode(';', $fileContentsByLine[$j]);
                        if(count($lineContents) <= 1)
                            continue;

                        $productNumber = $lineContents[5];
                        // if($articleNumber == '99999')
                        //     break;
                        $amount = $lineContents[6];
                        $amountAvailable = $lineContents[7];
                        // $amountDamaged = $lineContents[8];
                        // $amountClarification = $lineContents[9];
                        // $amountPostProcessing = $lineContents[10];
                        // $amountOther = $lineContents[11];
                        
                        $this->oiUtils->updateProduct($productNumber, $amountAvailable, $context);
                        $this->oiUtils->updateQSStock($lineContents, $productNumber, $context);
                        $this->oiUtils->updateDispoControlData($productNumber, intval($amount), $context);

                        if($amount != $amountAvailable)
                        {
                            $deleteFilesWhenFinished = false;
                            $this->oiUtils->sendErrorNotification('RM_WE WKE','Damaged or otherwise unusable products reported at position:<br> ' . $j . '<br><br>Check back with logistics partner to keep stock information up to date.', [$path . $filename]);
                        }
                    }
                }
            }
        }
        $this->oiUtils->archiveFiles($path,$deleteFilesWhenFinished,'ReceivedStatusReply/RM_WE/');
        return new Response('',Response::HTTP_NO_CONTENT);
    }    

    /**
     * @Route("/api/v{version}/_action/as-order-interface/pullArticleError", name="api.custom.as_order_interface.pullArticleError", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Pulls the article error response from remote sFTP server
     */
    public function pullArticleError(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Artikel_Error', $timeStamp);

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'Artikel_Error');
        return $this->checkArticleError($context);
    }
    /**
     * @Route("/api/v{version}/_action/as-order-interface/checkArticleError", name="api.custom.as_order_interface.checkArticleError", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Checks pulled article error response, iterates through them and send a notification eMail to administration
     */
    public function checkArticleError(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Artikel_Error', $timeStamp) . '/';
        if (file_exists($path)) {
            $files = scandir($path);
            if (count($files) > 2)
            {
                $filecontents = '';
                for ($i = 2; $i < count($files); $i++) {
                    $filename = $files[$i];
                    $filecontents = $filename . '|||' . $filecontents . file_get_contents($path . $filename);
                }
                $this->oiUtils->sendErrorNotification('Error: Article base','Error reported by logistics partner, submitted article base contains errors check logfile for further informations.', [$path . $filename]);
            }
        }
        $this->oiUtils->archiveFiles($path,false,'ReceivedStatusReply/Artikel_Error/');
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    /**
     * @Route("/api/v{version}/_action/as-order-interface/pullStock
    ", name="api.custom.as_order_interface.pullStock
    ", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Pulls the stock report from remote sFTP server
     */
    public function pullStock(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Bestand', $timeStamp);

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'Bestand');
        return $this->checkStock($context);
    }
    /**
     * @Route("/api/v{version}/_action/as-order-interface/checkStock", name="api.custom.as_order_interface.checkStock", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Processes pulled stock report
     * QS Marks
     * faulty - DF
     * postprocessing - NB
     * other - SO
     * clarification - KL
     */
    public function checkStock(Context $context): ?Response
    {
        $this->dispoController->updateDispoControlData($context);

        $deleteFilesWhenFinished = false; // since a false bool value is null for shopware we predefine the false value... 
        //get flag for deleting files when finished
        $deleteFilesWhenFinished = $this->systemConfigService->get('ASOrderInterface.config.deleteFilesAfterEvaluation');

        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Bestand', $timeStamp) . '/';
        if (file_exists($path)) {
            $files = scandir($path);
            for ($i = 2; $i < count($files); $i++) {
                $filename = $files[$i];
                $filenameContents = explode('_', $filename);

                $filecontents = file_get_contents($path . $filename);
                $fileContentsByLine = explode(PHP_EOL,$filecontents); 

                switch ($filenameContents[0])
                    {
                        case 'QSK':     
                            foreach ($fileContentsByLine as $entryID => $contentLine) {
                                $lineContents = explode(';', $contentLine);
                                if(count($lineContents) <= 1)
                                {
                                    continue;
                                }
                                $deleteFilesWhenFinished = false;
                                $this->oiUtils->sendErrorNotification('QSK', 'Change in quality status. EntryID: ' . $entryID, [$path . $filename]);
                                switch($lineContents[7])
                                {
                                    case 'KL': // klärfall / clarification
                                        switch($lineContents[9])
                                        {
                                            case 'NB': // to postprocessing
                                                $this->oiUtils->processQSK($lineContents[1],0,-intval($lineContents[5]),intval($lineContents[5]),0,0,$context); // intval($lineContents[5])
                                            break;
                                            case 'SO': // to other
                                                $this->oiUtils->processQSK($lineContents[1],0,-intval($lineContents[5]),0,intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'DF': // to faulty
                                                $this->oiUtils->processQSK($lineContents[1],intval($lineContents[5]),-intval($lineContents[5]),0,0,0,$context); // intval($lineContents[5])
                                            break;
                                            default:
                                            $this->oiUtils->processQSK($lineContents[1],0,-intval($lineContents[5]),0,0,intval($lineContents[5]),$context);
                                            break;
                                        }
                                    break;
                                    case 'NB': // Nachbearbeitung / postprocessing
                                        switch($lineContents[9])
                                        {
                                            case 'KL': // to clarification
                                                $this->oiUtils->processQSK($lineContents[1],0,intval($lineContents[5]),-intval($lineContents[5]),0,0,$context); // intval($lineContents[5])
                                            break;
                                            case 'SO': // to other
                                                $this->oiUtils->processQSK($lineContents[1],0,0,-intval($lineContents[5]),intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'DF': // to faulty
                                                $this->oiUtils->processQSK($lineContents[1],intval($lineContents[5]),0,-intval($lineContents[5]),0,0,$context); // intval($lineContents[5])
                                            break;
                                            default:
                                                $this->oiUtils->processQSK($lineContents[1],0,0,-intval($lineContents[5]),0,intval($lineContents[5]),$context); // intval($lineContents[5])
                                            break;
                                        }
                                    break;
                                    case 'SO': // Sonstige / otherwise
                                        switch($lineContents[9])
                                        {
                                            case 'NB': // to postprocessing
                                                $this->oiUtils->processQSK($lineContents[1],0,0,intval($lineContents[5]),-intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'KL': // to clarification
                                                $this->oiUtils->processQSK($lineContents[1],0,intval($lineContents[5]),0,-intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'DF': // to faulty
                                                $this->oiUtils->processQSK($lineContents[1],intval($lineContents[5]),0,0,-intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            default:
                                                $this->oiUtils->processQSK($lineContents[1],0,0,0,-intval($lineContents[5]),intval($lineContents[5]),$context); // intval($lineContents[5])
                                            break;
                                        }
                                    break;
                                    case 'DF': // Defekt // faulty
                                        switch($lineContents[9])
                                        {
                                            case 'NB': // to postprocessing
                                                $this->oiUtils->processQSK($lineContents[1],-intval($lineContents[5]),0,intval($lineContents[5]),0,0,$context); // intval($lineContents[5])
                                            break;
                                            case 'SO': // to other
                                                $this->oiUtils->processQSK($lineContents[1],-intval($lineContents[5]),0,0,intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'KL': // to clarification
                                                $this->oiUtils->processQSK($lineContents[1],-intval($lineContents[5]),intval($lineContents[5]),0,0,0,$context); // intval($lineContents[5])
                                            break;
                                            default:
                                                $this->oiUtils->processQSK($lineContents[1],-intval($lineContents[5]),0,0,0,intval($lineContents[5]),$context); // intval($lineContents[5])
                                            break;
                                        }
                                    break;
                                    default:
                                        switch($lineContents[9])
                                        {
                                            case 'KL': // to clarification
                                                $this->oiUtils->processQSK($lineContents[1],0,intval($lineContents[5]),0,0,-intval($lineContents[5]),$context); 
                                            break;
                                            case 'NB': // to postprocessing
                                                $this->oiUtils->processQSK($lineContents[1],0,0,intval($lineContents[5]),0,-intval($lineContents[5]),$context); 
                                            break;
                                            case 'SO': // to other
                                                $this->oiUtils->processQSK($lineContents[1],0,0,0,intval($lineContents[5]),-intval($lineContents[5]),$context); 
                                            break;
                                            case 'DF': // to faulty
                                                $this->oiUtils->processQSK($lineContents[1],intval($lineContents[5]),0,0,0,-intval($lineContents[5]),$context); 
                                            break;
                                        }
                                    break;
                                }
                            }
                        break;
                        case 'BESTAND': // Daily report of current available items
                            $uniqueProductNumbers = array();
                            $condensedArray = array();      
                            $prevValue = 0;
                            foreach ($fileContentsByLine as $contentLine) {
                                if($contentLine == "") // skip if line contains no information
                                    continue;

                                $contentLineFields = explode(';',$contentLine);
                                $productNumber = $contentLineFields[1];
                                if($productNumber == "99999") // skip if line contains information about stored files, they always have the product ID 99999
                                    continue;

                                if(!in_array($productNumber,$uniqueProductNumbers))
                                    array_push($uniqueProductNumbers,$productNumber);

                                $prevValue = array_key_exists($productNumber . '-' . "available", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "available"] : 0;
                                $condensedArray[$productNumber . '-' . "available"] = $prevValue + intval($contentLineFields[5]);

                                $prevValue = array_key_exists($productNumber . '-' . "qsFaulty", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "qsFaulty"] : 0;
                                $condensedArray[$productNumber . '-' . "qsFaulty"] = $prevValue + intval($contentLineFields[6]);

                                $prevValue = array_key_exists($productNumber . '-' . "qsClarification", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "qsClarification"] : 0;
                                $condensedArray[$productNumber . '-' . "qsClarification"] = $prevValue + intval($contentLineFields[7]);

                                $prevValue = array_key_exists($productNumber . '-' . "qsPostprocessing", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "qsPostprocessing"] : 0;
                                $condensedArray[$productNumber . '-' . "qsPostprocessing"] = $prevValue + intval($contentLineFields[8]);

                                $prevValue = array_key_exists($productNumber . '-' . "qsOther", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "qsOther"] : 0;
                                $condensedArray[$productNumber . '-' . "qsOther"] = $prevValue + intval($contentLineFields[9]);
                            }

                            $productCount = count($condensedArray) / 5; // amount of entries in the condensed array divided by the amount of unique entries per product

                            for($y = 0; $y < $productCount; $y++)
                            {
                                $productNumber = $uniqueProductNumbers[$y];

                                $available = $condensedArray[$productNumber . '-' . "available"];
                                $qsFaulty = $condensedArray[$productNumber . '-' . "qsFaulty"];
                                $qsClarification = $condensedArray[$productNumber . '-' . "qsClarification"];
                                $qsPostprocessing = $condensedArray[$productNumber . '-' . "qsPostprocessing"];
                                $qsOther = $condensedArray[$productNumber . '-' . "qsOther"];

                                /** @var ProductEntity $productEntity */
                                $productEntity = $this->oiUtils->getFilteredEntitiesOfRepository($this->container->get('product.repository'), 'productNumber', $productNumber, $context)->first();
                                if($productEntity == null)
                                {
                                    $this->oiUtils->sendErrorNotification('Stock feedback contains unknown product', 'A product mentioned in the daily stock feedback report is unkown.<br>Please check the stock feedback at line ' . $y, [$path . $filename]);
                                    continue;
                                }

                                /** @var OrderInterfaceStockQSEntity $stockQSEntity */
                                $stockQSEntity = $this->oiUtils->getFilteredEntitiesOfRepository($this->container->get('as_stock_qs.repository'), 'productId', $productEntity->getId(), $context)->first();
                                /** @var DispoControlDataEntity $dispoControlDataEntity */
                                $dispoControlDataEntity = $this->oiUtils->getFilteredEntitiesOfRepository($this->container->get('as_dispo_control_data.repository'), 'productId', $productEntity->getId(), $context)->first();
                                
                                $discrepancy = false;
                                $discrepancyValue = 0;
                                if($dispoControlDataEntity->getStockAvailable() != $available)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $dispoControlDataEntity->getStockAvailable() - $available;
                                }
                                if($stockQSEntity->getFaulty() != $qsFaulty)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $stockQSEntity->getFaulty() - $qsFaulty;
                                }
                                if($stockQSEntity->getClarification() != $qsClarification)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $stockQSEntity->getClarification() - $qsClarification;
                                }
                                if($stockQSEntity->getPostprocessing() != $qsPostprocessing)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $stockQSEntity->getPostprocessing() - $qsPostprocessing;
                                }
                                if($stockQSEntity->getOther() != $qsOther)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $stockQSEntity->getOther() - $qsOther;
                                }
                                if($discrepancy)
                                {
                                    $deleteFilesWhenFinished = false;
                                    $this->oiUtils->sendErrorNotification('Stock Feedback Discrepancy','Discrepancies found in stock feedback check logfile for further informations.<br>Articlenumber: ' . $productNumber . "<br>Discrepancy: " . $discrepancyValue, [$path . $filename]);
                                }
                            }                       
                        break;
                        case 'BS+': // addition of currently available items (items lost but found, etc.)
                            foreach ($fileContentsByLine as $entryID => $contentLine) {
                                $lineContents = explode(';', $contentLine);
                                if(count($lineContents) <= 1)
                                {
                                    continue;
                                }

                                $productNumber = $lineContents[1];

                                /** @var EntityRepositoryInterface $productRepository */
                                $productRepository = $this->container->get('product.repository');
                                /** @var ProductEntity $productEntity */
                                $productEntity = $this->oiUtils->getFilteredEntitiesOfRepository($productRepository, 'productNumber', $productNumber, $context)->first();
                                if($productEntity == null)
                                    continue;
                                switch($lineContents[7])
                                {
                                    case 'KL': // klärfall / clarification

                                        $this->oiUtils->updateQSStockBS(0,0,0,intval($lineContents[5]),$productEntity,$context);
                                        $this->oiUtils->sendErrorNotification('Stock Addition','Products added, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'NB': // Nachbearbeitung / postprocessing
                                        
                                        $this->oiUtils->updateQSStockBS(0,intval($lineContents[5]),0,0,$productEntity,$context);
                                        $this->oiUtils->sendErrorNotification('Stock Addition','Products added, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'SO': // Sonstige / other
                                        
                                        $this->oiUtils->updateQSStockBS(0,0,intval($lineContents[5]),0,$productEntity,$context);
                                        $this->oiUtils->sendErrorNotification('Stock Addition','Products added, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'DF': // Defekt // faulty
                                        
                                        $this->oiUtils->updateQSStockBS(intval($lineContents[5]),0,0,0,$productEntity,$context);
                                        $this->oiUtils->sendErrorNotification('Stock Addition','Products added, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    default:
                                        $current = $productEntity->getStock();

                                        $productRepository->update([
                                            ['id' => $productEntity->getId(), 'stock' => $current + intval($lineContents[5])],
                                        ],$context);
                                    break;
                                }
                            }
                        break;
                        case 'BS-': // subtraction of currently available items (lost, stolen, destroyed, etc.)    
                            foreach ($fileContentsByLine as $entryID => $contentLine) {
                                $lineContents = explode(';', $contentLine);
                                if(count($lineContents) <= 1)
                                {
                                    continue;
                                }

                                $productNumber = $lineContents[1];

                                /** @var EntityRepositoryInterface $productRepository */
                                $productRepository = $this->container->get('product.repository');
                                /** @var ProductEntity $productEntity */
                                $productEntity = $this->oiUtils->getFilteredEntitiesOfRepository($productRepository, 'productNumber', $productNumber, $context)->first();
                                if($productEntity == null)
                                    continue;
                                switch($lineContents[7])
                                {
                                    case 'KL': // klärfall / clarification
                                        
                                        $this->oiUtils->updateQSStockBS(0,0,0,intval($lineContents[5]),$productEntity,$context);
                                        $this->oiUtils->sendErrorNotification('Stock Subtraction','Products removed, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'NB': // Nachbearbeitung / postprocessing
                                        
                                        $this->oiUtils->updateQSStockBS(0,intval($lineContents[5]),0,0,$productEntity,$context);
                                        $this->oiUtils->sendErrorNotification('Stock Subtraction','Products removed, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'SO': // Sonstige / other
                                        
                                        $this->oiUtils->updateQSStockBS(0,0,intval($lineContents[5]),0,$productEntity,$context);
                                        $this->oiUtils->sendErrorNotification('Stock Subtraction','Products removed, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'DF': // Defekt // faulty
                                        
                                        $this->oiUtils->updateQSStockBS(intval($lineContents[5]),0,0,0,$productEntity,$context);
                                        $this->oiUtils->sendErrorNotification('Stock Subtraction','Products removed, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    default:
                                        $current = $productEntity->getStock();

                                        $productRepository->update([
                                            ['id' => $productEntity->getId(), 'stock' => $current - intval($lineContents[5])],
                                        ],$context);
                                    break;
                                }
                            }
                        break;
                        default:  
                        break;
                    }
            }
        }
        // $this->oiUtils->archiveFiles($path,$deleteFilesWhenFinished,'ReceivedStatusReply/Bestand/');
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    
    /**
     * @Route("/api/v{version}/_action/as-order-interface/healthPing", name="api.custom.as_order_interface.healthPing", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function healthPing(Context $context)
    {
        /** @var EntitySearchResult $scheduledTasks */
        $scheduledTasks = $this->oiUtils->getAllEntitiesOfRepository($this->container->get('scheduled_task.repository'), $context);
        /** @var ScheduledTaskEntity $scheduledTask */
        foreach($scheduledTasks as $taskID => $scheduledTask)
        {
            $scheduledTaskName = $scheduledTask->getName();
            if(! $this->oiUtils->isMyScheduledTaskCk($scheduledTaskName))
                continue;
                
            if($scheduledTask->getStatus() == 'failed')
            {
                $this->oiUtils->sendErrorNotification('Scheduled Task Failed', "Task $scheduledTaskName has failed.<br>Check dead messages for further informations.", ['']);
            }
        }

        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/as-order-interface/initStockQS", name="api.custom.as_order_interface.initStockQS", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function initStockQS(Context $context)
    {
        $data = null;
        $stockQSRepository = $this->container->get('as_stock_qs.repository');
        $products = $this->oiUtils->getAllEntitiesOfRepository($this->container->get('product.repository'), $context);
        foreach($products as $productID => $product)
        {
            if(!$this->oiUtils->entityExistsInRepositoryCk($stockQSRepository, 'productId', $productID, $context)){
                //add new entity because we havent found one
                $data[] = [ 'productId' => $productID, 
                            'faulty' => 0, 
                            'clarification' => 0, 
                            'postprocessing' => 0, 
                            'other' => 0];
            }
        }
        if($data != null)
            $stockQSRepository->create(
                $data,
                Context::createDefaultContext()
            );

        return new Response('',Response::HTTP_NO_CONTENT);
    }
    public function deleteStockQSEntry(string $productID, Context $context)
    {
        /** @var EntityRepositoryInterface $stockQSRepository */
        $stockQSRepository = $this->container->get('as_stock_qs.repository');

        if($this->oiUtils->entityExistsInRepositoryCk($stockQSRepository, 'productId', $productID, $context)){
            $entity = $this->oiUtils->getFilteredEntitiesOfRepository($stockQSRepository, 'productId', $productID, $context)->first();
            $stockQSRepository->delete([
                ['id' => $entity->getId()],
            ],$context);
        }

        return new Response('',Response::HTTP_NO_CONTENT);
    }
    
}