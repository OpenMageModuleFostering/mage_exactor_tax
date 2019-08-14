<?php
/**
 * User: LOGICIFY\corvis
 * Date: 4/20/12
 * Time: 1:12 PM
 */
 
class Exactor_Tax_Helper_Mapping extends Mage_Core_Helper_Abstract {

    const EUC_SHIPPING_COMMON_CARRIER='EUC-13010204';
    const EUC_SHIPPING_USPS='EUC-13030202';
    const EUC_SHIPPING_AND_HANDLING='EUC-13010101';
    const EUC_HANDLING = 'EUC-13010301';

    const MSG_DEFAULT_SHIPPING_NAME = 'Default Shipping';
    const MSG_HANDLING_FEE = 'Handling Fee';
    const MSG_SHIPPING_DESCRIPTION_PREFIX = 'Shipping Fee: ';
    const MSG_ESTIMATION_REQUEST = 'Magento Tax Estimation Request';
    const MSG_DISCOUNTED_BY = 'Discounted by $';

    const LINE_ITEM_ID_SHIPPING = "SHIPPING";
    const LINE_ITEM_ID_HANDLING = "HANDLING";

    const ATTRIBUTE_NAME_EXEMPTION = 'taxvat';

    const MAX_SKU_CODE_LENGTH = 16;

    private $logger;


    const PO_ESTIMATE_TEXT = 'Estimated Tax ';

    const UNKNOWN_STREET_TEXT = "";

    private function getLogger(){
        if ($this->logger==null)
            $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        return $this->logger;
    }

    function __construct()
    {
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
    }

    /**
     * @param $firstName
     * @param $lastName
     * @param $middleName
     * @return string
     */
    private function buildFullName($firstName, $lastName, $middleName=null){
        $parts = array($firstName, $middleName, $lastName);
        return join(' ', $parts);
    }

    public function buildExactorAddressForQuoteAddress(Mage_Sales_Model_Quote_Address $address){
        $exactorAddress = new AddressType();
        if ($address==null) return null;
        // Set defaults
        $exactorAddress->setStreet1(self::UNKNOWN_STREET_TEXT);
        $exactorAddress->setFullName("Unknown Buyer");
        //
        $fullName = trim($address->getName());//trim($this->buildFullName($address->getFirstname(), $address->getLastname(), $address->getMiddlename()));
        if (strlen($fullName)>0)
            $exactorAddress->setFullName($fullName);
        if ($address->getStreetFull() != null)
            $exactorAddress->setStreet1($address->getStreetFull());
        $exactorAddress->setCity($address->getCity());
        $exactorAddress->setStateOrProvince($address->getRegion());
        $exactorAddress->setCountry($address->getCountry());
        $exactorAddress->setPostalCode($address->getPostcode());
        return $exactorAddress;
    }

    public function getSKUForItem(Mage_Sales_Model_Quote_Item $magentoItem,
                                  Exactor_Core_Model_MerchantSettings $merchantSettings){
        $sku='';
        switch ($merchantSettings->getSourceOfSKU()){
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_NONE:
                $sku = '';
                break;
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_SKU_FIELD:
                $sku = $magentoItem->getSku();;
                break;
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_ATTRIBUTE_NAME:
                $attributeSetName = 'Default';
                try{
                    $attributeSetModel = Mage::getModel("eav/entity_attribute_set");
                    $attributeSetModel->load($magentoItem->getProduct()->getAttributeSetId());
                    $attributeSetName  = $attributeSetModel->getAttributeSetName();
                }catch(Exception $e){}
                $sku = $attributeSetName;
                break;
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_PRODUCT_CATEGORY:
                $category = $magentoItem->getProduct()->getCategory();
                if ($category != null)
                    $sku = $category->getName();
                break;
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_TAX_CLASS:
                /** @var Mage_Tax_Model_Mysql4_Class_Collection $taxClassCollection  */
                $taxClassCollection = Mage::getModel('tax/class')->getCollection();
                /** @var Mage_Tax_Model_Class $taxClass  */
                $taxClass = $taxClassCollection->getItemById($magentoItem->getProduct()->getTaxClassId());
                if ($taxClass == null) $sku = ''; else $sku = $taxClass->getClassName();
                break;
        }
        return substr($sku,0, self::MAX_SKU_CODE_LENGTH); // Max length for SKU is 16 characters
    }

    private function isUSPSShipping($methodName){
        $uspsShippingNames = array('USPS', 'Mail', 'Post', 'USPostal');
        // Remove all spaces and dots from the original name to simplify search
        $methodName = preg_replace('/[\.\s]/','',$methodName);
        foreach($uspsShippingNames as $currName){
            if (stristr($methodName, $currName)) return true;
        }
        return false;
    }

    public function getShippingLineItem(Mage_Sales_Model_Quote_Address $quoteAddress,
                                        Exactor_Core_Model_MerchantSettings $merchantSettings){
        if ($quoteAddress->getAddressType() == Mage_Sales_Model_Quote_Address::TYPE_BILLING) return null; // There is no shipping fees there
        if ($quoteAddress->getShippingAmount()==0) return null;
        $shippingLineItem = new LineItemType();
        $shippingLineItem->setDescription(self::MSG_SHIPPING_DESCRIPTION_PREFIX . $quoteAddress->getShippingDescription());
        if (trim($shippingLineItem->getDescription())==''){
            $shippingLineItem->setDescription(self::MSG_DEFAULT_SHIPPING_NAME);
        } 
        // Get EUC code for shipping
        $shippingEUC = self::EUC_SHIPPING_COMMON_CARRIER;
        if ($merchantSettings->isShippingIncludeHandling()){
            $shippingEUC = self::EUC_SHIPPING_AND_HANDLING;
        }else if ($this->isUSPSShipping($quoteAddress->getShippingDescription())){
            $shippingEUC = self::EUC_SHIPPING_USPS;
        }
        $shippingLineItem->setSKU($shippingEUC);
        // Other fields
        $shippingLineItem->setId(self::LINE_ITEM_ID_SHIPPING);
        $shippingLineItem->setQuantity(1);
        // If shipping doesn't include handling we should subtract handling from the total shipping amount
        $amount = $quoteAddress->getShippingAmount();
        if (!$merchantSettings->isShippingIncludeHandling()){
            $amount -= $this->getHandlingFeeByMethodName($quoteAddress->getShippingMethod());
        }
        $shippingLineItem->setGrossAmount($amount);
        $this->applyDiscountToLineItem($shippingLineItem,$quoteAddress->getShippingDiscountAmount());
        return $shippingLineItem;
    }

    /**
     * Returns handling feed amount by given name, or 0 if there is no handling
     * @param $name
     * @return void
     */
    private function getHandlingFeeByMethodName($name){
        if (strpos($name,'_')) $name = substr($name,0,strpos($name,'_'));
        // Fetch carriers information from Magento config to determine handling amount
        $carriers = Mage::getStoreConfig('carriers');
        if (!array_key_exists($name, $carriers)) return 0;
        foreach($carriers as $id => $carrier){
            if (array_key_exists('handling_fee', $carrier) ){
                if ($id == $name)
                    return $carrier['handling_fee'];
            }
        }
        return 0;
    }

    public function getHandlingLineItem(Mage_Sales_Model_Quote_Address $quoteAddress,
                                        Exactor_Core_Model_MerchantSettings $merchantSettings){
        if ($quoteAddress->getAddressType() == Mage_Sales_Model_Quote_Address::TYPE_BILLING) return null; // There is no shipping fees there
        if ($merchantSettings->isShippingIncludeHandling()) return null; // Handling already included in the shipping
        $handlingLineItem = new LineItemType();
        $handlingLineItem->setId(self::LINE_ITEM_ID_HANDLING);
        $handlingLineItem->setDescription(self::MSG_HANDLING_FEE);
        $handlingLineItem->setSKU(self::EUC_HANDLING);
        $handlingLineItem->setQuantity(1);
        $handlingLineItem->setGrossAmount($this->getHandlingFeeByMethodName($quoteAddress->getShippingMethod()));
        if ($handlingLineItem->getGrossAmount()==0) return null;
        return $handlingLineItem;
    }

    /**
     * @param \Mage_Sales_Model_Quote_Address_Item|\Mage_Sales_Model_Quote_Item $magentoItem
     * @param Mage_Sales_Model_Quote_Address $quoteAddress
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return LineItemType
     */
    public function buildLineItemForMagentoItem(Mage_Sales_Model_Quote_Item $magentoItem,
                                                Mage_Sales_Model_Quote_Address $quoteAddress,
                                                Exactor_Core_Model_MerchantSettings $merchantSettings){
        $lineItem = new LineItemType();
        $lineItem->setDescription($magentoItem->getName());
        $lineItem->setGrossAmount($magentoItem->getBaseRowTotal());
        $lineItem->setQuantity($magentoItem->getTotalQty());
        $lineItem->setSKU($this->getSKUForItem($magentoItem, $merchantSettings));
        $this->applyDiscountToLineItem($lineItem, $magentoItem->getDiscountAmount());
        return $lineItem;
    }

    public function applyDiscountToLineItem(LineItemType &$item, $discountAmount=0){
        if ($discountAmount>0){
            $discountedLine = self::MSG_DISCOUNTED_BY . $discountAmount;
            $item->setDescription($item->getDescription() . " ($discountedLine)");
            $item->setGrossAmount($item->getGrossAmount() - $discountAmount);
        }
    }

    public function getExemptionIdForQuoteAddress(Mage_Sales_Model_Quote_Address $quoteAddress,
                                                  Exactor_Core_Model_MerchantSettings $merchantSettings){
        $exemptionId = '';
        if ($merchantSettings->getExemptionsSupported()){
            $customerExemptionId = $quoteAddress->getQuote()->getCustomer()->getData(self::ATTRIBUTE_NAME_EXEMPTION);
            if ($customerExemptionId!=null) $exemptionId=$customerExemptionId;
        }
        return $exemptionId;
    }

    private function getCurrentCurrencyCode(){
        $currency = Mage::app()->getStore()->getBaseCurrencyCode();
        if ($currency == null) $currency = 'USD';
        return $currency;
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $quoteAddress
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @param bool $isMultishipping
     * @return InvoiceRequestType
     */
    public function buildInvoiceRequestForQuoteAddress(Mage_Sales_Model_Quote_Address $quoteAddress,
                                                       Exactor_Core_Model_MerchantSettings $merchantSettings,
                                                       $isMultishipping){
        // Building Invoice Parts
        $shipToAddress = $this->buildExactorAddressForQuoteAddress($quoteAddress);
        // Trying to find billing address in the quote
        $billingAddress = $this->buildExactorAddressForQuoteAddress($quoteAddress->getQuote()->getBillingAddress());
        $isEstimation = !$billingAddress->hasData();
        if ($isEstimation) $shipToAddress->setFullName(self::MSG_ESTIMATION_REQUEST);
        // If this is just tax estimation for not logged in user
        // we just need to use shipping as billing
        if ($isEstimation){
            $billingAddress = $shipToAddress;
        }
        // If shipping info unavailable - fallback to billing information
        if (!$shipToAddress->hasData()) $shipToAddress=$billingAddress;
        if (!$billingAddress->hasData() || !$shipToAddress->hasData()) return null;
        // Composing invoice object
        $invoiceRequest = new InvoiceRequestType();
        $invoiceRequest->setSaleDate(new DateTime());
        $invoiceRequest->setPurchaseOrderNumber(self::PO_ESTIMATE_TEXT .$quoteAddress->getId());
        $invoiceRequest->setShipTo($shipToAddress);
        $invoiceRequest->setBillTo($billingAddress);
        $invoiceRequest->setCurrencyCode($this->getCurrentCurrencyCode());
        $invoiceRequest->setShipFrom($merchantSettings->getExactorShippingAddress());
        $invoiceRequest->setExemptionId($this->getExemptionIdForQuoteAddress($quoteAddress, $merchantSettings));
        // Line items list
        $magentoItems = $quoteAddress->getAllItems();
        $itemNum = 0;
        foreach ($magentoItems as $magentoItem){
            $exactorLineItem = $this->buildLineItemForMagentoItem($magentoItem, $quoteAddress, $merchantSettings);
            $exactorLineItem->setId('_' . $itemNum++);
            // If this is non-multishipping request we should set
            // ship to address to billing address for VIRTUAL ITEMS
            if (!$isMultishipping){
                if ($magentoItem->getProduct()->getIsVirtual()){
                    // Previouse requirements was to set  BillTo address for wi as Shipping info for virtual products
                    /*$virtualShipToAddress = $invoiceRequest->getShipTo(); //( $isEstimation ? $invoiceRequest->getShipFrom() : $billingAddress);
                    $exactorLineItem->setShipTo($virtualShipToAddress);
                    $exactorLineItem->setBillTo($virtualShipToAddress);*/
                }
            }
            $invoiceRequest->addLineItem($exactorLineItem);
        }
        // Shipping & Handling
        $invoiceRequest->addLineItem($this->getShippingLineItem($quoteAddress, $merchantSettings));
        $invoiceRequest->addLineItem($this->getHandlingLineItem($quoteAddress, $merchantSettings));
        return $invoiceRequest;
    }
}