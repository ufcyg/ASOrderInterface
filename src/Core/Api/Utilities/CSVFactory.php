<?php declare(strict_types=1);

namespace ASOrderInterface\Core\Api\Utilities;

use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationEntity;
use Symfony\Component\PropertyAccess\PropertyAccess;

/*

Class solemny for generating large strings according to the documentation of logistics partner

*/
class CSVFactory
{
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    /** @var PropertyAccess $propertyAccessor */
    private $properyAccessor;
    /** @var string $companyID */
    private $companyID;
    /** @var Context $currentContext */
    private $currentContext;
    /** @var OrderInterfaceUtils $oiUtils */
    private $oiUtils;
    /** @var ContainerInterface $container */
    protected $container;
    public function __construct(SystemConfigService $systemConfigService,
                                OrderInterfaceUtils $oiUtils)
    {
        $this->properyAccessor = PropertyAccess::createPropertyAccessor();
        $this->systemConfigService = $systemConfigService;
        $this->companyID = $this->systemConfigService->get('ASOrderInterface.config.logisticsCustomerID');
        $this->oiUtils = $oiUtils;
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

    public function generateArticlebase(string $csvString, ProductEntity $product, Context $context): string
    {
        $this->currentContext = $context;
        $translationEntity = $this->getProductTranslation($product);
        $customFields = $this->getProductCustomField($translationEntity);
        $placeholder = '';                                                                                      // (maximum)Length
        $csvString  .= $this->truncateString($this->companyID . '.Artikelstamm',30) . ';';           // Kennung* (30)Length
        $csvString  .= $this->truncateString($product->getProductNumber(),28) . ';';                 // Artikelnummer* (28)
        $csvString  .= $this->truncateString($placeholder . ';',28);                                 // Matchcode (28)Length
        $csvString  .= $this->truncateString($this->getProductName($translationEntity),30) . ';';    // Artikelbezeichnung 1* (30)Length
        $csvString  .= $this->truncateString($placeholder,30) . ';';                                 // Artikelbezeichnung 2 (30)Length
        $csvString  .= $this->truncateString($placeholder,30) . ';';                                 // Artikelbezeichnung 3 (30)Length
        $csvString  .= $this->truncateString($placeholder,6) . ';';                                  // Warengruppe (6)Length
        $csvString  .= $this->truncateString($this->getUnit($product->getUnitId()),3) . ';';         // Basismengeneinheit* (3)Length
        $csvString  .= str_replace('.',',',strval($product->getWeight())) . ';';                                                          // Basismengeneinheit, Gewicht in KG, netto (9.5)Length
        $csvString  .= str_replace('.',',',strval($product->getWeight())) . ';';                     // Basismengeneinheit, Gewicht in KG, brutto (9.5)Length
        $csvString  .= str_replace('.',',',strval($product->getLength())) . ';';                     // Basismengeneinheit, Länge in mm (5.2)Length
        $csvString  .= str_replace('.',',',strval($product->getWidth())) . ';';                      // Basismengeneinheit, Breite in mm (5.2)Length
        $csvString  .= str_replace('.',',',strval($product->getHeight())) . ';';                     // Basismengeneinheit, Höhe in mm (5.2)Length
        $csvString  .= $this->truncateString($placeholder,3) . ';';                                  // Verpackungseinheit (VE) Mengeneinheit (3)Length
        $csvString  .= $product->getMinPurchase() . ';';                                             // VE Menge (8.3)Length
        $csvString  .= $placeholder . ';';                                                           // VE Mengeneinheit Gewicht in KG, netto (9.5)Length
        $csvString  .= $placeholder . ';';                                                           // VE Mengeneinheit Gewicht in KG, brutto (9.5)Length
        $csvString  .= $placeholder . ';';                                                           // VE Mengeneinheit, Länge in mm (5.2)Length
        $csvString  .= $placeholder . ';';                                                           // VE Mengeneinheit, Breite in mm (5.2)Length
        $csvString  .= $placeholder . ';';                                                           // VE Mengeneinheit, Höhe in mm (5.2)Length
        $csvString  .= $this->truncateString($placeholder,3) . ';';                                  // Lademittel (LM) Typ (3)Length
        $csvString  .= $placeholder . ';';                                                           // LM Menge (8.3)Length
        $csvString  .= $placeholder . ';';                                                           // LM Mengeneinheit, Gewicht in KG, netto (9.5)Length
        $csvString  .= $placeholder . ';';                                                           // LM Mengeneinheit, Gewicht in KG, brutto (9.5)Length
        $csvString  .= $placeholder . ';';                                                           // LM Mengeneinheit, Länge in mm (5.2)Length
        $csvString  .= $placeholder . ';';                                                           // LM Mengeneinheit, Breite in mm (5.2)Length
        $csvString  .= $placeholder . ';';                                                           // (LM Mengeneinheit, Höhe in mm 5.2)Length
        $csvString  .= $this->truncateString($product->getEan(),14) . ';';                           // EAN Nummer 1 (14)Length
        $csvString  .= $this->truncateString($placeholder,14) . ';';                                 // EAN Nummer 2 (14)Length
        $csvString  .= $this->truncateString($placeholder,14) . ';';                                 // EAN Nummer 3 (14)Length
        $csvString  .= $this->truncateString($placeholder,14) . ';';                                 // EAN Nummer 4 (14)Length
        $csvString  .= $this->truncateString($placeholder,14) . ';';                                 // EAN Nummer 5 (14)Length
        $csvString  .= $this->truncateString($placeholder,14) . ';';                                 // EAN Nummer VE (14)Length
        $csvString  .= $this->truncateString($placeholder,18) . ';';                                 // Lief.Artikelnummer 1 (18)Length
        $csvString  .= $this->truncateString($placeholder,18) . ';';                                 // Lief.Artikelnummer 2 (18)Length
        $csvString  .= $this->truncateString($placeholder,18) . ';';                                 // Lief.Artikelnummer 3 (18)Length
        $csvString  .= $this->truncateString($placeholder,18) . ';';                                 // Lief.Artikelnummer 4 (18)Length
        $csvString  .= $this->truncateString($placeholder,18) . ';';                                 // Lief.Artikelnummer 5 (18)Length
        if ($customFields != null)
        {
            if (array_key_exists('custom_rieck_properties_MHD', $customFields)) 
            {
                if($customFields['custom_rieck_properties_MHD'] == 0)
                {
                    $csvString  .= '0' . ';'; 
                }
                else
                {
                    $csvString  .= '1' . ';';                                                        // MHD Pflicht? (1)Length
                }                                                          
            } 
            else 
            {$csvString  .= '0' . ';';}
            if (array_key_exists('custom_rieck_properties_MHD_WE', $customFields)) 
            {
                if($customFields['custom_rieck_properties_MHD_WE'] == 0)
                {
                    $csvString  .= ';';
                }
                else
                {
                    $csvString  .= $customFields['custom_rieck_properties_MHD_WE'] . ';';                // MHD Restlaufzeit, WE (5)Length
                }                
            } 
            else 
            {$csvString  .= ';';}
            if (array_key_exists('custom_rieck_properties_MHD_WA', $customFields)) 
            {
                if($customFields['custom_rieck_properties_MHD_WA'] == 0)
                {
                    $csvString  .= ';';
                }
                else
                {
                    $csvString  .= $customFields['custom_rieck_properties_MHD_WA'] . ';';                // MHD Restlaufzeit WA (5)Length
                }
            } 
            else 
            {$csvString  .= ';';}
            if (array_key_exists('custom_rieck_properties_MHD', $customFields)) 
            {
                if($customFields['custom_rieck_properties_MHD_WE'] == 0)
                {
                    $csvString  .= ';';
                }
                else
                {
                    $csvString  .= $customFields['custom_rieck_properties_MHD'] . ';';                   // Maximale Haltbarkeit (5)Length
                }                  
            } 
            else 
            {$csvString  .= ';';}
            if (array_key_exists('custom_rieck_properties_batched', $customFields)) 
            {
                if ($customFields['custom_rieck_properties_batched']) 
                {
                    $csvString  .= '1' . ';';
                } 
                else 
                {
                    $csvString  .= '0' . ';';// Chargen Pflicht? (1)Length
                }                                                           
            } 
            else 
            {$csvString  .= ';';}
        }
        else
        {$csvString  .= '0' . ';' . ';' . ';' . ';' . '0' . ';';}
        
        
        $csvString  .= '0' . ';';                                                                    // S/N Erfassung WE? (1)Length
        $csvString  .= '0' . ';';                                                                    // S/N Erfassung WA? (1)Length
        $csvString  .= $placeholder . ';';                                                           // Einzelpreis (7.4)Length
        $csvString  .= $placeholder . ';';                                                           // Zolltarifnummer (25)Length
        $csvString  .= $placeholder . ';';                                                           // Ursprungsland (3)Length
        $csvString  .= $this->getManufacturerName($product) . ';';                                   // Hersteller (15)Length
        $csvString  .= $placeholder . ';';                                                           // Bemerkung (78)Length
        $csvString  .= "\n";
        
        return $csvString;
    }

    private function getUnit(string $unitID):string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('unitId', $unitID));
        /** @var EntityRepositoryInterface $unitTranslationRepository */
        $unitTranslationRepository = $this->container->get('unit_translation.repository');
        /** @var EntitySearchResult $unitTranslationSearchResult */
        $unitTranslationSearchResult = $unitTranslationRepository->search($criteria,Context::createDefaultContext());
        /** @var UnitTranslationEntity $unitTranslation */
        $unitTranslation = $unitTranslationSearchResult->first();
        return $unitTranslation->getShortCode();
    }

    private function truncateString($truncation,int $maxValue):string
    {
        if(is_int($truncation))
        {
            return substr(strval($truncation),0,$maxValue);
        }
        if($truncation != '' && $truncation != null)
        {
            return substr($truncation,0,$maxValue);
        }
        return '';
    }

    private function getManufacturerName(ProductEntity $product):string
    {
        $manufacturerID = $product->getManufacturerId();
        $manufacturerTranslationRepository = $this->container->get('product_manufacturer_translation.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productManufacturerId',$manufacturerID));
        $manufacturer = $manufacturerTranslationRepository->search($criteria,$this->currentContext);
        $manufacturer = $manufacturer->first();
        
        return $manufacturer != null ? $manufacturer->getName() : '';
    }
    
    private function getProductTranslation(ProductEntity $product):ProductTranslationEntity
    {
        $productID = $product->getId();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId',$productID));
        /** @var EntityRepositoryInterface $productTranslationRepository */
        $productTranslationRepository = $this->container->get('product_translation.repository');

        $entities = $productTranslationRepository->search($criteria,$this->currentContext);
        $context = $this->currentContext;
        /** @var ProductTranslationEntity $translationEntity */
        foreach($entities as $translationEntity)
        {// TODO language
            $translationEntity;
        }
        return $translationEntity;
    }

    private function getProductName(ProductTranslationEntity $translationEntity):string
    {//maxlength 30
        return $translationEntity->getName();
    }

    private function getProductCustomField(ProductTranslationEntity $translationEntity)
    {//maxlength 30
        return $translationEntity->getCustomFields();
    }

    /* Generation of header for orders according to the documentation of the logistics partner */
    public function generateHeader(array $associativeArray, string $orderNumber, string $csvString, string $customerID, Context $context): string
    {
        $this->currentContext = $context;
        
        $placeholder = '';                                                                                                                      //FIELD-ID MAXLENGTH
        $csvString  .= $this->truncateString($this->companyID,30) . '.WAAvis.Kopf' . ';';                                            //Kennung 30
        $csvString  .= $this->truncateString($orderNumber,25) . ';';                                                                 //Auftragsnummer Kunde 25
        $csvString  .= $this->truncateString($placeholder,8) . ';';                                                                  //Bereitstelldatum 8
        $csvString  .= $this->truncateString($placeholder,6) . ';';                                                                  //Bereitstelluhrzeit 6
        $csvString  .= $this->truncateString($placeholder,30) . ';';                                                                 //Referenz 1 30
        $csvString  .= $this->truncateString($placeholder,30) . ';';                                                                 //Referenz 2 30
        $csvString  .= $this->truncateString($placeholder,30) . ';';                                                                 //Referenz 3 30
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[firstNameCustomer]'),35) . ';';   //Name 1, Kunde 35
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[lastNameCustomer]'),35) . ';';    //Name 2, Kunde 35
        $csvString  .= $this->truncateString($placeholder,35) . ';';                                                                 //Name 3, Kunde 35
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[streetCustomer]'),45) . ';';      //Straße, Kunde 45
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[zipCodeCustomer]'),10) . ';';     //PLZ, Kunde 10
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[cityCustomer]'),35) . ';';        //Ort, Kunde 35
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[countryISOalpha2Customer]'),3) . ';';//Land, Kunde 3
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[firstNameDelivery]'),35) . ';';   //Name 1, Lieferanschrift 35
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[lastNameDelivery]'),35) . ';';    //Name 2, Lieferanschrift 35
        $csvString  .= $this->truncateString($placeholder,35) . ';';                                                                 //Name 3, Lieferanschrift 35
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[streetDelivery]'),45) . ';';      //Straße, Lieferanschrift 45
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[zipCodeDelivery]'),10) . ';';     //PLZ, Lieferanschrift 10
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[cityDelivery]'),35) . ';';        //Ort, Lieferanschrift 35
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[countryISOalpha2Delivery]'),3) . ';';//Land, Lieferanschrift 3
        $csvString  .= $this->truncateString($this->properyAccessor->getValue($associativeArray, '[eMail]'),55) . ';';               //Mailadresse, Lieferanschrift 55
        $csvString  .= $this->truncateString($placeholder,20) . ';';                                                                 //Telefon, Lieferanschrift 20
        $csvString  .= $this->truncateString($placeholder,8) . ';';                                                                  //Fixtermindatum 8
        $csvString  .= $this->truncateString($placeholder,6) . ';';                                                                  //Fixterminuhrzeit 6
        $csvString  .= $this->truncateString($placeholder,35) . ';';                                                                 //Speditionshinweis 1 35
        $csvString  .= $this->truncateString($placeholder,30) . ';';                                                                 //Speditionshinweis 1, Zusatzhinweis 30
        $csvString  .= $this->truncateString($placeholder,35) . ';';                                                                 //Speditionshinweis 2 35
        $csvString  .= $this->truncateString($placeholder,30) . ';';                                                                 //Speditionshinweis 2, Zusatzhinweis 30
        $csvString  .= $this->truncateString($placeholder,3) . ';';                                                                  //Frankatur 3
        $csvString  .= $this->truncateString($placeholder,25) . ';';                                                                 //Frankatur, Zusatzinformation 25
        $csvString  .= $this->getOrderValue($associativeArray) . ';';                                                                //Warenwert 7.4
        $csvString  .= $this->truncateString('UPS',4) . ';';                                                                  //Versandart LFS 4
        $csvString  .= $this->truncateString($placeholder,4) . ';';                                                                  //Servicecode LFS 4
        $csvString  .= $this->truncateString($placeholder,6) . ';';                                                                  //Tour LFS 6
        $csvString  .= '1' . ';';                                                                                                    //Schnittstelle LFS 1
        $csvString  .= $this->truncateString('02',2) . ';';                                                                          //Priorität 2
        $csvString  .= "\n";

        return $csvString;
    }

    /* Generating of order details according to the documentation of the logistics partner */
    public function generateDetails(array $associativeArray, string $orderNumber, int $i, $csvString, bool $orderCancelled, Context $context): string
    {
        $this->currentContext = $context;
        $accessstring = '[' . $i . ']';
        /** @var OrderLineItemEntity $product */
        $product = $this->properyAccessor->getValue($associativeArray, $accessstring);
        $placeholder = '';                                                                                  //FIELD-ID MAXLENGTH
        $csvString  .= $this->truncateString($this->companyID,30) . '.WAAvis.Detail' . ';';      //Kennung 30
        $csvString  .= $this->truncateString($orderNumber,25) . ';';                             //Auftragsnummer Kunde 25
        $csvString  .= $this->truncateString($product->getPosition(),6) . ';';                   //Auftragspositionsnummer Kunde 6
        $csvString  .= $this->truncateString($this->getArticleNumber($product),28) . ';';        //Artikelnummer 28
        
        if($orderCancelled)
        {
            $csvString  .= '0' . ';';                                            //Gesamtmenge in Basismengeneinheit 8.3
        }
        else
        {
            $csvString  .= $product->getQuantity() . ';';                                            //Gesamtmenge in Basismengeneinheit 8.3
        }
        $csvString  .= $this->truncateString($placeholder,46) . ';';                             //Externe NVE 46
        $csvString  .= $this->truncateString($placeholder,8) . ';';                              //MHD 8
        $csvString  .= $this->truncateString($placeholder,15) . ';';                             //Charge 15
        $csvString  .= $this->truncateString($placeholder,2) . ';';                              //Qualitätsstatus 2
        $csvString  .= $placeholder . ';';                                                       //Seriennummern  Delimiter |
        $csvString  .= $this->truncateString($placeholder,30) . ';';                             //Referenz 1 30
        $csvString  .= $this->truncateString($placeholder,30) . ';';                             //Referenz 2 30
        $csvString  .= $this->truncateString($placeholder,30) . ';';                             //Referenz 3 30
        $csvString  .= "\n";

        return $csvString;
    }

    private function getOrderValue(array $associativeArray): string //7.4
    {
        $orderValue = 0;
        for ($i = 0; $i < count($associativeArray)-13; $i++) //hardcoded value is equivalent to amount of array entries in "$associativeArray" before products are added
        {
            $product = $associativeArray[$i];
            $orderValue += $this->properyAccessor->getValue($product, 'totalPrice');
        }
        return str_replace('.',',',strval($orderValue));
    }

    private function getArticleNumber(OrderLineItemEntity $product): string
    {
        $payload = $product->getPayload();
        $articleNumber = $this->properyAccessor->getValue($payload, '[productNumber]');
        if (!is_string($articleNumber))
        {
            $articleNumber = '';
        }
        return $articleNumber;
    }
    
}