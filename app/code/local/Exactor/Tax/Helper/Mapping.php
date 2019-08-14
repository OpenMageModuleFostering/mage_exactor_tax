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
    const EUC_NON_TAXABLE = 'EUC-99990101';
    const EUC_GIFT_CARD = self::EUC_NON_TAXABLE;
    const EUC_STORE_CREDIT = self::EUC_NON_TAXABLE;

    const MSG_DEFAULT_SHIPPING_NAME = 'Default Shipping';
    const MSG_HANDLING_FEE = 'Handling Fee';
    const MSG_ADJUSTMENTS = 'Adjustments';
    const MSG_SHIPPING_DESCRIPTION_PREFIX = 'Shipping Fee: ';
    const MSG_ESTIMATION_REQUEST = 'Magento Tax Estimation Request';
    const MSG_DISCOUNTED_BY = 'Discounted by $';
    const MSG_ADJUSTMENTS_REFUND = "Adjustments Refund";
    const MSG_GIFT_CARD_ITEM = "Gift card(-s)";
    const MSG_STORE_CREDIT_ITEM = "Store credit";

    const LINE_ITEM_ID_SHIPPING = "SHIPPING";
    const LINE_ITEM_ID_HANDLING = "HANDLING";
    const LINE_ITEM_ID_ADJUSTMENTS = "ADJUSTMENTS";
    const LINE_ITEM_ID_GIFT_CARD = "GIFT_CARD";
    const LINE_ITEM_ID_STORE_CREDIT = "STORE_CREDIT";
    const INDEXED_LINE_ITEM_ID_PREFIX = '_';

    const ATTRIBUTE_NAME_EXEMPTION = 'taxvat';

    const MAX_SKU_CODE_LENGTH = 32;

    private $logger;


    const PO_ESTIMATE_TEXT = 'Estimated Tax ';

    const UNKNOWN_STREET_TEXT = "";
    const UNKNOWN_STATE_NAME = "UNKNOWN";
    const UNKNOWN_ZIP_CODE = "00000";


    const PRICE_TYPE_DYNAMIC = 0;

    /**
     * @param Mage_Sales_Model_Order_Item $magentoItem
     * @return string
     */
    public function buildExactorItemId($magentoItem)
    {
        $itemId = $magentoItem->getId();
        if ((defined('EXACTOR_BUILD_CUSTOM_ITEM_KEY') && constant('EXACTOR_BUILD_CUSTOM_ITEM_KEY')) || empty($itemId)){
            $itemId = base64_encode(join(':', array($magentoItem->getSku(), $magentoItem->getProductId(),
                $magentoItem->getRowTotal(), $magentoItem->getQtyOrdered())));
            $this->getLogger()->info("Building custom item key: " . $itemId);
        }
        return self::INDEXED_LINE_ITEM_ID_PREFIX . $itemId;
    }

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

    /**
     * @param Mage_Customer_Model_Address_Abstract $address
     * @return AddressType|null
     */
    private function buildExactorAddressForAbstractAddress($address){
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
        $exactorAddress->setStateOrProvince($address->getRegionCode());
        $exactorAddress->setCountry($address->getCountry());
        $exactorAddress->setPostalCode($address->getPostcode());
        // It is possible that postal code or state will be missing (e.g. the tax estimation form)
        // In this case we will try to determine region basing on the given Postal Code
        if (strlen(trim($exactorAddress->getStateOrProvince()))==0 && strlen(trim($exactorAddress->getPostalCode()))!=0){
            $exactorAddress->setStateOrProvince(RegionResolver::getInstance()->getStateOrProvinceByCode(trim($exactorAddress->getPostalCode())));
        }
        if (!$exactorAddress->hasData()) return null;
        return $exactorAddress;
    }

    /**
     * @param Mage_Sales_Model_Order_Address $address
     * @return AddressType|null
     */
    public function buildExactorAddressForOrderAddress($address){
        return $this->buildExactorAddressForAbstractAddress($address);
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $address
     * @return AddressType|null
     */
    public function buildExactorAddressForQuoteAddress($address){
        return $this->buildExactorAddressForAbstractAddress($address);
    }

    /**
     * @param Mage_Sales_Model_Quote_Item_Abstract $magentoItem
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return string
     */
    public function getSKUForItem($magentoItem, $merchantSettings){
        $sku='';
        $product = $magentoItem->getProduct();
        if ($product == null)
            $product = Mage::getModel('catalog/product')
                ->setStoreId($magentoItem->getStoreId())
                ->load($magentoItem->getProductId());
        switch ($merchantSettings->getSourceOfSKU()){
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_NONE:
                $sku = '';
                break;
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_SKU_FIELD:
                $sku = $magentoItem->getSku();
                break;
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_ATTRIBUTE_NAME:
                $attributeSetName = 'Default';
                try{
                    $attributeSetModel = Mage::getModel("eav/entity_attribute_set");
                    $attributeSetModel->load($product->getAttributeSetId());
                    $attributeSetName  = $attributeSetModel->getAttributeSetName();
                }catch(Exception $e){}
                $sku = $attributeSetName;
                break;
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_PRODUCT_CATEGORY:
                $category = $product->getCategory();
                if ($category != null)
                    $sku = $category->getName();
                break;
            case Exactor_Core_Model_MerchantSettings::SKU_SOURCE_TAX_CLASS:
                /** @var Mage_Tax_Model_Mysql4_Class_Collection $taxClassCollection  */
                $taxClassCollection = Mage::getModel('tax/class')->getCollection();
                /** @var Mage_Tax_Model_Class $taxClass  */
                $taxClass = $taxClassCollection->getItemById($product->getTaxClassId());
                if ($taxClass == null) $sku = ''; else $sku = $taxClass->getClassName();
                break;
        }
        return substr($sku, self::PRICE_TYPE_DYNAMIC, self::MAX_SKU_CODE_LENGTH); // Max length for SKU is 32 characters
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

    /**
     * @param Mage_Sales_Model_Quote_Address $quoteAddress
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return LineItemType|null
     */
    public function getShippingLineItemForQuoteAddress($quoteAddress, $merchantSettings) {

        if ($quoteAddress->getAddressType() == Mage_Sales_Model_Quote_Address::TYPE_BILLING) return null; // There is no shipping fees there
        if ($quoteAddress->getShippingAmount()==0) return null;
        $shippingLineItem = $this->getShippingLineItem($merchantSettings, $quoteAddress->getShippingMethod(),
                                               $quoteAddress->getShippingDescription(),
                                               $quoteAddress->getShippingAmount(),
                                               $quoteAddress->getShippingDiscountAmount());
        return $shippingLineItem;
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return LineItemType|null
     */
    public function getShippingLineItemForCreditMemo($creditMemo, $merchantSettings){
        if ($creditMemo->getShippingAmount()==0) return null;
        $lineItem = $this->getShippingLineItem($merchantSettings, $creditMemo->getOrder()->getShippingMethod(),
                                          $creditMemo->getOrder()->getShippingDescription(),
                                          $creditMemo->getShippingAmount());
        // For refunds we shouldn't subtract handling amount
        $lineItem->setGrossAmount($creditMemo->getShippingAmount());
        return $lineItem;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return LineItemType|null
     */
    public function getShippingLineItemForOrder($order, $merchantSettings) {
        if ($order->getBaseShippingAmount() == 0) return null;
        $lineItem = $this->getShippingLineItem($merchantSettings, $order->getShippingMethod(),
                                          $order->getShippingDescription(),
                                          $order->getBaseShippingAmount());
        return $lineItem;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return LineItemType|null
     */
    public function getShippingLineItemForInvoice($invoice, $merchantSettings) {
        if ($invoice->getBaseShippingAmount() == 0) return null;
        return $this->getShippingLineItemForOrder($invoice->getOrder(), $merchantSettings);
    }

    /**
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @param $carrierName
     * @param $carrierDescription
     * @param $amount
     * @param int $discount
     * @return LineItemType
     */
    private function getShippingLineItem($merchantSettings, $carrierName, $carrierDescription, $amount, $discount=0){
        $shippingLineItem = new LineItemType();
        $shippingLineItem->setDescription(self::MSG_SHIPPING_DESCRIPTION_PREFIX . $carrierDescription);
        if (trim($shippingLineItem->getDescription())==''){
            $shippingLineItem->setDescription(self::MSG_DEFAULT_SHIPPING_NAME);
        }
        // Get EUC code for shipping
        $shippingEUC = self::EUC_SHIPPING_COMMON_CARRIER;
        if ($merchantSettings->isShippingIncludeHandling()){
            $shippingEUC = self::EUC_SHIPPING_AND_HANDLING;
        }else if ($this->isUSPSShipping($carrierName)){
            $shippingEUC = self::EUC_SHIPPING_USPS;
        }
        $shippingLineItem->setSKU($shippingEUC);
        // Other fields
        $shippingLineItem->setId(self::LINE_ITEM_ID_SHIPPING);
        $shippingLineItem->setQuantity(1);
        // If shipping doesn't include handling we should subtract handling from the total shipping amount
        if (!$merchantSettings->isShippingIncludeHandling()){
            $amount -= $this->getHandlingFeeByMethodName($carrierName);
        }
        $shippingLineItem->setGrossAmount($amount);
        $this->applyDiscountToLineItem($shippingLineItem,$discount);
        return $shippingLineItem;
    }



    /**
     * Returns handling feed amount by given name, or 0 if there is no handling
     * @param $name
     * @return void
     */
    private function getHandlingFeeByMethodName($name){
        if (strpos($name, "_")) $name = substr($name, self::PRICE_TYPE_DYNAMIC,strpos($name, "_"));
        if ($name == null) $name="";
        // Fetch carriers information from Magento config to determine handling amount
        $carriers = Mage::getStoreConfig('carriers');
        if (!array_key_exists($name, $carriers)) return self::PRICE_TYPE_DYNAMIC;
        foreach($carriers as $id => $carrier){
            if (array_key_exists('handling_fee', $carrier)){
                if ($id == $name)
                    return $carrier['handling_fee'];
            }
        }
        return self::PRICE_TYPE_DYNAMIC;
    }

    /**
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @param $carrierName
     * @return LineItemType|null
     */
    public function getHandlingLineItem($merchantSettings, $carrierName){
        if ($merchantSettings->isShippingIncludeHandling()) return null; // Handling already included in the shipping
        $handlingLineItem = new LineItemType();
        $handlingLineItem->setId(self::LINE_ITEM_ID_HANDLING);
        $handlingLineItem->setDescription(self::MSG_HANDLING_FEE);
        $handlingLineItem->setSKU(self::EUC_HANDLING);
        $handlingLineItem->setQuantity(1);
        $handlingLineItem->setGrossAmount($this->getHandlingFeeByMethodName($carrierName));
        if ($handlingLineItem->getGrossAmount()== self::PRICE_TYPE_DYNAMIC) return null;
        return $handlingLineItem;
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $quoteAddress
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return LineItemType|null
     */
    private function getHandlingLineItemForQuoteAddress($quoteAddress, $merchantSettings){
        if ($quoteAddress->getAddressType() == Mage_Sales_Model_Quote_Address::TYPE_BILLING) return null; // There is no shipping fees there
        return $this->getHandlingLineItem($merchantSettings, $quoteAddress->getShippingMethod());
    }

    /**
     * @param \Mage_Sales_Model_Quote_Address_Item|\Mage_Sales_Model_Quote_Item $magentoItem
     * @param Mage_Sales_Model_Quote_Address $quoteAddress
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return LineItemType
     */
    public function buildLineItemForMagentoItem($magentoItem,
                                                $quoteAddress,
                                                $merchantSettings){
        $lineItem = new LineItemType();
        $lineItem->setDescription($magentoItem->getName());
        if ($magentoItem->getBaseRowTotal())
            $lineItem->setGrossAmount($magentoItem->getBaseRowTotal());
        else
            $lineItem->setGrossAmount(0.0);
        $lineItem->setQuantity($magentoItem->getTotalQty());
        if ($magentoItem instanceof Mage_Sales_Model_Order_Creditmemo_Item){
            $lineItem->setSKU($this->getSKUForItem($magentoItem->getOrderItem(), $merchantSettings));
        }else{
            $lineItem->setSKU($this->getSKUForItem($magentoItem, $merchantSettings));
        }
        if (!$this->isDiscountExempt($magentoItem)) {
            $this->applyDiscountToLineItem($lineItem, $magentoItem->getDiscountAmount());
        }
        // Check if it is bundle or groped product
        $product = $magentoItem->getProduct();
        if (empty($product) && $magentoItem->getOrderItem() != null) {
            $product = $magentoItem->getOrderItem()->getProduct();
        }
        if (empty($product) && $magentoItem->getProductId() != null) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId($magentoItem->getStoreId())
                ->load($magentoItem->getProductId());
        } 
        if (in_array($product->getTypeId(), array(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE))) {
            if ($product->getPriceType() == self::PRICE_TYPE_DYNAMIC) {
                $lineItem->setGrossAmount(0);
                $lineItem->setDescription($lineItem->getDescription() . " :: Dynamic Price");
                return null; // Doesn't show it in the Exactor transaction.
            }
        }
        return $lineItem;
    }

    /**
     * @param  \Mage_Sales_Model_Quote_Address_Item|\Mage_Sales_Model_Quote_Item $magentoItem
     * @return void
     */
    private function isDiscountExempt($magentoItem){
        try {
            $config = ExactorPluginConfig::getInstance();
            if (!$config->getFeatureConfig()->isFeatureEnabled(EXACTOR_CONFIG_FEATURE_EXEMPT_DISCOUNTS)) return false;
            $exempted = $config->get(EXACTOR_CONFIG_FEATURE_EXEMPT_DISCOUNTS);
            if (!isset($exempted)) return false;
            if ($magentoItem->getOrderItem() != null){
                $magentoItem = $magentoItem->getOrderItem();
            }
            $actual = preg_split('/,/', $magentoItem->getAppliedRuleIds());
            foreach ($actual as $actualId) {
                if (in_array((int)$actualId, $exempted)) {
                    return true;
                }
            }
        } catch (Exception $e) {
            // Nothing to do. just log
            $this->logger->error("Error while determining if discount is exempt:", $e->getMessage(), 'isDiscountExempt');
        }
        return false;
    }

    /**
     * @param LineItemType $item
     * @param int $discountAmount
     */
    private function applyDiscountToLineItem($item, $discountAmount=0){
        if ($discountAmount > 0){
            $discountedLine = self::MSG_DISCOUNTED_BY . $discountAmount;
            $item->setDescription($item->getDescription() . " ($discountedLine)");
            $item->setGrossAmount($item->getGrossAmount() - $discountAmount);
        }
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $quoteAddress
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return mixed|string
     */
    private function getExemptionIdForQuoteAddress($quoteAddress,
                                                   $merchantSettings){
        $exemptionId = '';
        if ($merchantSettings->getExemptionsSupported()){
            $customerExemptionId = $quoteAddress->getQuote()->getCustomer()->getData(self::ATTRIBUTE_NAME_EXEMPTION);
            if ($customerExemptionId!=null) $exemptionId=$customerExemptionId;
        }
        return $exemptionId;
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return string
     */
    private function getExemptionIdForCreditMemo($creditMemo,
                                                 $merchantSettings){
        $exemptionId = '';
        if ($merchantSettings->getExemptionsSupported()){
            $customerExemptionId = $creditMemo->getOrder()->getCustomerTaxvat();
            if ($customerExemptionId!=null) $exemptionId=$customerExemptionId;
        }
        return $exemptionId;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return string
     */
    private function getExemptionIdForOrder($order,
                                            $merchantSettings){
        $exemptionId = '';
        if ($merchantSettings->getExemptionsSupported()){
            $customerExemptionId = $order->getCustomerTaxvat();
            if ($customerExemptionId!=null) $exemptionId=$customerExemptionId;
        }
        return $exemptionId;
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $quoteAddress
     * @return string
     */
    private function getCurrentCurrencyCode($quoteAddress){
        $store = Mage::app()->getStore();
        if ($quoteAddress->getQuote() != null && $quoteAddress->getQuote()->getStoreId() != null){
            $store = $quoteAddress->getQuote()->getStore();
        }
        return $this->getCurrencyCodeForStore($store);
    }

    /**
     * @param Mage_Core_Model_Store $store
     * @return string
     */
    private function getCurrencyCodeForStore($store){
        $currency = 'USD';
        if ($store != null){
            $currency = $store->getBaseCurrencyCode()!=null ? $store->getBaseCurrencyCode() : $currency;
        }
        return $currency;
    }

    private function buildGiftCardLineItem($amount){
        $lineItem = new LineItemType();
        $lineItem->setGrossAmount($amount);
        $lineItem->setQuantity(1);
        $lineItem->setSKU(self::EUC_GIFT_CARD);
        $lineItem->setId(self::LINE_ITEM_ID_GIFT_CARD);
        $lineItem->setDescription(self::MSG_GIFT_CARD_ITEM);
        return $lineItem;
    }

    private function buildStoreCreditLineItem($amount){
        $lineItem = new LineItemType();
        $lineItem->setGrossAmount($amount);
        $lineItem->setQuantity(1);
        $lineItem->setSKU(self::EUC_STORE_CREDIT);
        $lineItem->setId(self::LINE_ITEM_ID_STORE_CREDIT);
        $lineItem->setDescription(self::MSG_STORE_CREDIT_ITEM);
        return $lineItem;
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $quoteAddress
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @param bool $isMultishipping
     * @param $isEstimation
     * @return InvoiceRequestType
     */
    public function buildInvoiceRequestForQuoteAddress($quoteAddress,
                                                       $merchantSettings,
                                                       $isMultishipping, $isEstimation){
        // Building Invoice Parts
        $shipToAddress = $this->buildExactorAddressForQuoteAddress($quoteAddress);
        // Trying to find billing address in the quote
        $billingAddress = $this->buildExactorAddressForQuoteAddress($quoteAddress->getQuote()->getBillingAddress());

        if ($isEstimation)
            $shipToAddress->setFullName(self::MSG_ESTIMATION_REQUEST);
        // If this is just tax estimation for not logged in user
        // we just need to use shipping as billing
        if ($isEstimation){
            $billingAddress = $shipToAddress;
        }
        // If shipping info unavailable - fallback to billing information
        if ($shipToAddress == null || !$shipToAddress->hasData()) $shipToAddress=$billingAddress;
        if ($shipToAddress == null || !$shipToAddress->hasData()) $shipToAddress=null;
        if ($shipToAddress == null) return null;
        // Composing invoice object
        $invoiceRequest = new InvoiceRequestType();
        $invoiceRequest->setSaleDate(new DateTime());
        $invoiceRequest->setPurchaseOrderNumber(self::PO_ESTIMATE_TEXT .$quoteAddress->getId());
        $invoiceRequest->setShipTo($shipToAddress);
        $invoiceRequest->setBillTo($billingAddress);
        $invoiceRequest->setCurrencyCode($this->getCurrentCurrencyCode($quoteAddress));
        $invoiceRequest->setShipFrom($merchantSettings->getExactorShippingAddress());
        $invoiceRequest->setExemptionId($this->getExemptionIdForQuoteAddress($quoteAddress, $merchantSettings));
        // Line items list
        $magentoItems = $quoteAddress->getAllItems();
        $itemNum = 0;
        /**
         * @var $magentoItem Mage_Sales_Model_Quote_Item
         */
        foreach ($magentoItems as $magentoItem) {
            $exactorLineItem = $this->buildLineItemForMagentoItem($magentoItem, $quoteAddress, $merchantSettings);
            if ($exactorLineItem == null) continue;
            $exactorLineItem->setId($this->buildExactorItemId($magentoItem));
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
        // Gift Cards
        if ($quoteAddress->getBaseGiftCardsAmount() != null && $quoteAddress->getBaseGiftCardsAmount() != 0){
            $invoiceRequest->addLineItem($this->buildGiftCardLineItem(-1 * $quoteAddress->getBaseGiftCardsAmount()));
        }
        // Store Credit
        $storeCreditAmount = $quoteAddress->getBaseCustomerBalanceAmount();
        if ($storeCreditAmount != null && $storeCreditAmount != 0) {
            $invoiceRequest->addLineItem($this->buildStoreCreditLineItem(-1 * $storeCreditAmount));
        }
        // Shipping & Handling
        $invoiceRequest->addLineItem($this->getShippingLineItemForQuoteAddress($quoteAddress, $merchantSettings));
        $invoiceRequest->addLineItem($this->getHandlingLineItemForQuoteAddress($quoteAddress, $merchantSettings));
        return $invoiceRequest;
    }

    /**
     * Calculates prorated shipping and handling amounts for partial payments \ refunds.
     *
     * @param $orderedShippingAmount        - total shipping amount from the order. Typically getOrder()->getBaseShippingAmount()
     * @param $alreadyProcessedAmount       - a part of shipping amount that was already processed, for instance $creditMemo->getOrder()->getBaseShippingRefunded()
     * @param $targetShippingAmount         - shipping amount to process, e.g. $invoice->getBaseShippingAmount()
     * @param $handlingTotalAmount          - total handling amount
     * @param Mage_Core_Model_Store $store
     * @return array (shipping amount, handling amount)
     */
    private function calculateProratedShippingAndHandling($orderedShippingAmount, $alreadyProcessedAmount,
                                                          $targetShippingAmount, $handlingTotalAmount,
                                                          $store) {
        // $orderedShippingAmount = $invoice->getOrder()->getBaseShippingAmount();
        // $alreadyProcessedAmount = $invoice->getOrder()->getBaseShippingRefunded();
        // $targetShippingAmount =  $invoice->getBaseShippingAmount();
        // $handlingTotalAmount = $handlingLineItem->getGrossAmount();
        $delta = $orderedShippingAmount - $alreadyProcessedAmount + $targetShippingAmount;
        $shippingFactor = $targetShippingAmount / $delta;
        $handlingAmount = $handlingTotalAmount*$shippingFactor;
        $shippingAmount = $targetShippingAmount - $handlingAmount;
        return array($store->roundPrice($shippingAmount),
                     $store->roundPrice($shippingAmount));
    }

    /** Return invoice requests by credit memo. In case if credit memo contains adjustments - returns 2 separated invoices
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return array InvoiceRequestType
     */
    public function buildInvoiceRequestsForCreditMemo($creditMemo, $merchantSettings){
        $result = array();
        $invoiceRequest = new InvoiceRequestType();
        $invoiceRequest->setBillTo($this->buildExactorAddressForOrderAddress($creditMemo->getBillingAddress()));
        $invoiceRequest->setShipTo($this->buildExactorAddressForOrderAddress($creditMemo->getShippingAddress()));
        $invoiceRequest->setShipFrom($merchantSettings->getExactorShippingAddress());
        $invoiceRequest->setCurrencyCode($creditMemo->getOrder()->getOrderCurrencyCode());
        $invoiceRequest->setPurchaseOrderNumber($creditMemo->getOrder()->getIncrementId());
        $invoiceRequest->setSaleDate($this->getCreatedAtDateForMageOrder($creditMemo->getOrder()));
        $invoiceRequest->setExemptionId($this->getExemptionIdForCreditMemo($creditMemo, $merchantSettings));
        // Line items
        $magentoItems = $creditMemo->getAllItems();
        foreach ($magentoItems as $magentoItem){
            $exactorLineItem = $this->buildLineItemForMagentoItem($magentoItem, new Mage_Sales_Model_Quote_Address(), $merchantSettings);
            if ($exactorLineItem != null){
                $exactorLineItem->setQuantity($magentoItem->getQty());
            }
            $invoiceRequest->addLineItem($exactorLineItem);
        }
        // Shipping & Handling
        $shippingLineItem = $this->getShippingLineItemForCreditMemo($creditMemo, $merchantSettings);
        $handlingLineItem = null;

        if ($shippingLineItem!=null) {
            $handlingLineItem = $this->getHandlingLineItem($merchantSettings, $creditMemo->getOrder()->getShippingMethod());
            if ($handlingLineItem!=null){   // TODO: use common method: calculateProratedShippingAndHandling(...)
                $delta = $creditMemo->getOrder()->getBaseShippingAmount() - $creditMemo->getOrder()->getBaseShippingRefunded() + $creditMemo->getBaseShippingAmount();
                $shippingFactor = $creditMemo->getBaseShippingAmount() / $delta;
                $handlingAmount = $handlingLineItem->getGrossAmount()*$shippingFactor;
                $shippingAmount = $creditMemo->getBaseShippingAmount() - $handlingAmount;
                // Update amounts
                $shippingLineItem->setGrossAmount($creditMemo->getStore()->roundPrice($shippingAmount));
                $handlingLineItem->setGrossAmount($creditMemo->getStore()->roundPrice($handlingAmount));
            }
        }

        $invoiceRequest->addLineItem($shippingLineItem);
        $invoiceRequest->addLineItem($handlingLineItem);

        $result[] = $invoiceRequest;
        // Adjustments
        $adjustmentsItem = new LineItemType();
        $adjustmentsItem->setId(self::LINE_ITEM_ID_ADJUSTMENTS);
        $adjustmentsItem->setDescription(self::MSG_ADJUSTMENTS);
        $adjustmentsItem->setQuantity(1);
        $adjustmentsItem->setSKU(self::EUC_NON_TAXABLE);
        $adjustmentsItem->setGrossAmount($creditMemo->getAdjustmentPositive()-$creditMemo->getAdjustmentNegative());
        if ($adjustmentsItem->getGrossAmount() != 0
                || $creditMemo->getBaseGiftCardsAmount() != null && $creditMemo->getBaseGiftCardsAmount() != 0
                || $creditMemo->getBaseCustomerBalanceAmount() != null && $creditMemo->getBaseCustomerBalanceAmount() != 0
        ) {
            $adjustmentInvoice = clone $invoiceRequest;
            $adjustmentInvoice->setPurchaseOrderNumber(self::MSG_ADJUSTMENTS_REFUND);
            $adjustmentInvoice->setLineItems(array());
            if ($adjustmentsItem->getGrossAmount() != 0){
                $adjustmentInvoice->addLineItem($adjustmentsItem);
            }
            // Gift Cards
            if ($creditMemo->getBaseGiftCardsAmount() != null && $creditMemo->getBaseGiftCardsAmount() != 0){
                $adjustmentInvoice->addLineItem($this->buildGiftCardLineItem(-1 * $creditMemo->getBaseGiftCardsAmount()));
            }
            // Store Credit
            $storeCreditAmount = $creditMemo->getBaseCustomerBalanceAmount();
            if ($storeCreditAmount != null && $storeCreditAmount != 0) {
                $adjustmentInvoice->addLineItem($this->buildStoreCreditLineItem(-1 * $storeCreditAmount));
            }
            $result[] = $adjustmentInvoice;
        }
        return $result;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string $default
     * @return DateTime
     */
    private function getCreatedAtDateForMageOrder($order, $default='now') {
        $res = $default;
        try{
            $res= '@'.$order->getCreatedAtDate()->getTimestamp();
        } catch (Exception $e) {
            $this->logger->error("Can't get date. Using default value." . $e->getMessage(),
                                 'getCreatedAtDateForMageInvoice');
        }
        return new DateTime($res);
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return array
     */
    public function buildInvoiceRequestForMagentoInvoice($invoice, $merchantSettings){
        $result = array();
        $invoiceRequest = new InvoiceRequestType();
        $invoiceRequest->setBillTo($this->buildExactorAddressForOrderAddress($invoice->getBillingAddress()));
        $invoiceRequest->setShipTo($this->buildExactorAddressForOrderAddress($invoice->getShippingAddress()));
        $invoiceRequest->setShipFrom($merchantSettings->getExactorShippingAddress());
        $invoiceRequest->setCurrencyCode($invoice->getOrder()->getOrderCurrencyCode());
        $invoiceRequest->setPurchaseOrderNumber($invoice->getOrder()->getIncrementId());
        $invoiceRequest->setSaleDate($this->getCreatedAtDateForMageOrder($invoice->getOrder()));
        $invoiceRequest->setExemptionId($this->getExemptionIdForOrder($invoice->getOrder(), $merchantSettings));
        //$invoiceRequest->setExemptionId($this->getExemptionIdForCreditMemo($creditMemo, $merchantSettings));
        // Line items
        $magentoItems = $invoice->getAllItems();
        foreach ($magentoItems as $magentoItem){
            $exactorLineItem = $this->buildLineItemForMagentoItem($magentoItem, new Mage_Sales_Model_Quote_Address(), $merchantSettings);
            if ($exactorLineItem != null){
                $exactorLineItem->setQuantity($magentoItem->getQty());
            }
            $invoiceRequest->addLineItem($exactorLineItem);
        }
        // Shipping & Handling
        $shippingLineItem = $this->getShippingLineItemForInvoice($invoice, $merchantSettings);
        $handlingLineItem = null;
        if ($shippingLineItem!=null) {
            $handlingLineItem = $this->getHandlingLineItem($merchantSettings, $invoice->getOrder()->getShippingMethod());
        }
        $invoiceRequest->addLineItem($shippingLineItem);
        $invoiceRequest->addLineItem($handlingLineItem);
        // Gift cards
        if ($invoice->getBaseGiftCardsAmount() != null && $invoice->getBaseGiftCardsAmount() != 0){
            $invoiceRequest->addLineItem($this->buildGiftCardLineItem(-1 * $invoice->getBaseGiftCardsAmount()));
        }
        // Store Credit
        $storeCreditAmount = $invoice->getBaseCustomerBalanceAmount();
        if ($storeCreditAmount != null && $storeCreditAmount != 0) {
            $invoiceRequest->addLineItem($this->buildStoreCreditLineItem(-1 * $storeCreditAmount));
        }
        $result[] = $invoiceRequest;
        return $result;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return array
     */
    public function buildInvoiceRequestForMagentoOrder($order, $merchantSettings){
        $result = array();
        $invoiceRequest = new InvoiceRequestType();
        $invoiceRequest->setBillTo($this->buildExactorAddressForOrderAddress($order->getBillingAddress()));
        $invoiceRequest->setShipTo($this->buildExactorAddressForOrderAddress($order->getShippingAddress()));
        $invoiceRequest->setShipFrom($merchantSettings->getExactorShippingAddress());
        $invoiceRequest->setCurrencyCode($order->getOrderCurrencyCode());
        $invoiceRequest->setPurchaseOrderNumber($order->getIncrementId());
        $invoiceRequest->setSaleDate($this->getCreatedAtDateForMageOrder($order));
        $invoiceRequest->setExemptionId($this->getExemptionIdForOrder($order, $merchantSettings));
        // Line items
        $magentoItems = $order->getAllItems();
        foreach ($magentoItems as $magentoItem){
            $exactorLineItem = $this->buildLineItemForMagentoItem($magentoItem, new Mage_Sales_Model_Quote_Address(), $merchantSettings);
            if ($exactorLineItem != null){
                $exactorLineItem->setQuantity($magentoItem->getQtyOrdered());
                $exactorLineItem->setId($this->buildExactorItemId($magentoItem));
            }
            $invoiceRequest->addLineItem($exactorLineItem);
        }
        // Shipping & Handling
        $shippingLineItem = $this->getShippingLineItemForOrder($order, $merchantSettings);
        $handlingLineItem = null;
        if ($shippingLineItem!=null) {
            $handlingLineItem = $this->getHandlingLineItem($merchantSettings, $order->getShippingMethod());
        }
        $invoiceRequest->addLineItem($shippingLineItem);
        $invoiceRequest->addLineItem($handlingLineItem);
        // Gift cards
        if ($order->getBaseGiftCardsAmount() != null && $order->getBaseGiftCardsAmount() != 0){
            $invoiceRequest->addLineItem($this->buildGiftCardLineItem(-1 * $order->getBaseGiftCardsAmount()));
        }
        // Store Credit
        $storeCreditAmount = $order->getBaseCustomerBalanceAmount();
        if ($storeCreditAmount != null && $storeCreditAmount != 0) {
            $invoiceRequest->addLineItem($this->buildStoreCreditLineItem(-1 * $storeCreditAmount));
        }
        $result[] = $invoiceRequest;
        return $result;
    }

    /**
     * @param array $filter
     * @param Mage_Customer_Model_Address_Abstract|AddressType $address
     * @return bool
     */
    public function isAllowedLocation($filter, $address) {
        /* Filter has the following structure:
            countryName1: array(state1, state2, state3)
            countryName1:  array(state1, state2, state3)
        */
        if (!isset($filter)) return true;
        if ($address instanceof Mage_Customer_Model_Address_Abstract) {
            if (array_key_exists($address->getCountry(), $filter)) {
                $states = $filter[$address->getCountry()];
                return in_array($address->getRegionCode(), $states);
            }
        } else {
            if (array_key_exists($address->getCountry(), $filter)) {
                $states = $filter[$address->getCountry()];
                return in_array($address->getStateOrProvince(), $states);
            }
        }
        return false;
    }

    /**
     * @param AddressType $address
     * @return bool
     */
    private function isAddessFullyPopulated($address) {
        if ($address==null || !$address->hasData()) return false;
        return strlen(trim($address->getStreet1())) > 0
            && strlen(trim($address->getFullName())) > 0
            && strlen(trim($address->getCity())) > 0;
    }

    /**
     * Returns true only in case when billTo and shipTo contains address line and full name.
     * Otherwise - returns false.
     * This method can be used to determine if current request is just tax estimation
     *
     * @param InvoiceRequestType $invoice
     * @return bool
     */
    public function isInvoiceAddressesFullyPopulated($invoice) {
        if ($invoice == null) return false;
        return $this->isAddessFullyPopulated($invoice->getShipTo());
            //&& $this->isAddessFullyPopulated($invoice->getBillTo());
    }
}