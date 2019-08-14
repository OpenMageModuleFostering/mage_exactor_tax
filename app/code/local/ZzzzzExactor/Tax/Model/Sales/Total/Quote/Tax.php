<?php

/**
 * This class will be used by Magento Core to collect Tax total per address
 * In magento 1.3 and earlier   Mage_Tax_Model_Sales_Total_Quote_Tax
 */
class ZzzzzExactor_Tax_Model_Sales_Total_Quote_Tax extends Mage_Sales_Model_Quote_Address_Total_Abstract
{ //Mage_Tax_Model_Sales_Total_Quote_Tax { //{

    const MODEL_MERCHANT_SETTINGS = "ZzzzzExactor_Core_Model_MerchantSettings";

    const LOG_MESSAGE_TAX_CALC_FAILED = 'Tax calculation failed due to the following reason: ';

    const MSG_GENERAL_ERROR = 'An error has occurred while processing the sales taxes for this transaction. Please contact technical support if this problem persists. Code: ';

    /**
     * @var IExactorLogger
     */
    private $logger;

    /**Show message to the buyer on errors listed below, all other Exactor errors will be displayed as General errors
     * @see self::MSG_GENERAL_ERROR
     * @var array
     */
    private $notifyUserErrorCodes;

    /**
     * Tax module helper
     *
     * @var Mage_Tax_Helper_Data
     */
    protected $_helper;

    /**
     * @var ZzzzzExactor_Tax_Helper_Calculation
     */
    private $exactorTaxCalculation;

    /**
     * @var ZzzzzExactor_Tax_Helper_Mapping
     */
    private $exactorMappingHelper;

    /** @var Mage_Core_Model_Session_Abstract */
    private $session;

    /**
     * @var ZzzzzExactor_Core_Helper_SessionCache
     */
    private $exactorSessionCache;
    /**
     * Tax configuration object
     *
     * @var Mage_Tax_Model_Config
     */
    protected $_config;

    /** @var \ZzzzzExactor_ExactorSettings_Helper_Data */
    private $exactorSettingsHelper;

    private function setupExactorCommonLibrary()
    {
        $libDir = Mage::getBaseDir("lib") . '/ExactorCommons';
        require_once($libDir . '/XmlProcessing.php');
        require_once($libDir . '/ExactorDomainObjects.php');
        require_once($libDir . '/ExactorCommons.php');
        require_once($libDir . '/RegionResolver.php');
        // Magento specific definitions
        require_once($libDir . '/Magento.php');
        require_once($libDir . '/config.php');
    }

    public function __construct()
    {
        $this->setCode('tax');
        $this->_helper = Mage::helper('tax');
        $this->_config = Mage::getSingleton('tax/config');
        $this->setupExactorCommonLibrary();
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        $this->exactorTaxCalculation = Mage::helper('tax/calculation');
        $this->exactorMappingHelper = Mage::helper('tax/mapping');
        $this->exactorSessionCache = Mage::helper('ZzzzzExactor_Core_SessionCache/');
        $this->exactorSettingsHelper = Mage::helper('ExactorSettings');
        $this->session = Mage::getSingleton('core/session', array('name' => 'frontend'));
        $this->notifyUserErrorCodes = array(ErrorResponseType::ERROR_MISSING_LINE_ITEMS,
            ErrorResponseType::ERROR_INVALID_SHIP_TO_ADDRESS,
            ErrorResponseType::ERROR_INVALID_CURRENCY_CODE);
    }


    /**
     * Load merchantSettings from the database
     * @param Mage_Sales_Model_Quote_Address $address
     * @return ZzzzzExactor_Core_Model_MerchantSettings
     */
    public function loadMerchantSettings($address = null)
    {
        $storeViewId = $address->getQuote()->getStoreId(); //Mage::app()->getStore()->getId();
        return $this->exactorSettingsHelper->loadValidMerchantSettings($storeViewId);
    }

    /**
     * Return True if there is multi-shipping request
     * @return bool
     */
    private function isMultishippingRequest()
    {
        $controller = Mage::app()->getRequest()->getControllerName();
        return (strstr("multishipping", $controller) > 0);
    }

    /**
     * Collect totals process.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Mage_Sales_Model_Quote_Address_Total_Abstract
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        //parent::collect($address);
        $this->_setAddress($address);
        if (count($address->getAllItems()) <= 0) return; // Skip addresses without items
        if ($address->getId() == null && !trim($address->getPostcode())) return; // Skip if there is no address
        //$this->_setAmount(0);
        //$this->logger->trace('Called for address #' . $address->getId() . ' (' . $address->getAddressType() . ')','collect');
        $merchantSettings = $this->loadMerchantSettings($address);

        $internalTaxCalculator = Mage::getSingleton("Mage_Tax_Model_Sales_Total_Quote_Tax");
        if ($merchantSettings == null) {
            /*$this->applyTax(0);
            $this->resetTaxForItems($address);*/
            $this->logger->info(self::LOG_MESSAGE_TAX_CALC_FAILED . ' Missing or invalid Merchant Settings. Pass request to internal Mage tax calc. system', 'collect');
            return $internalTaxCalculator->collect($address);
            //return $this->processTaxCalculationFail('Missing or invalid Merchant Settings');
        }
        // Preparing Exactor Invoice Request
        $invoiceRequest = $this->exactorMappingHelper->buildInvoiceRequestForQuoteAddress($address, $merchantSettings, $this->isMultishippingRequest(), $this->isEstimation());
        // Check request filter if it is enabled
        $config = ExactorPluginConfig::getInstance();
        if ($config->getFeatureConfig()->isFeatureEnabled(EXACTOR_CONFIG_FEATURE_TRN_FILTER)) {
            if (!$this->exactorMappingHelper->isAllowedLocation($config->get(EXACTOR_CONFIG_TRN_FILTER), $address)) {
                $this->logger->info('Rejected by Exactor filter. Skipping tax calculation. Internal tax calculation will be used instead.', 'collect');
                return $internalTaxCalculator->collect($address);
            }
        }
        $this->logger->trace('Invoice ' . serialize($invoiceRequest), 'collect');
        if ($invoiceRequest != null && $this->checkIfCalculationNeeded($invoiceRequest, $merchantSettings)) {
            if ($config->getFeatureConfig()->isFeatureEnabled(EXACTOR_CONFIG_FEATURE_DISABLE_ESTIMATES)) {
                if (!$this->exactorMappingHelper->isInvoiceAddressesFullyPopulated($invoiceRequest)) {
                    $this->logger->info('Skipping tax estimate due to the Exactor plug-in configuration', 'collect');
                    return $internalTaxCalculator->collect($address);
                }
            }
            // Sending to Exactor Tax Calculation Request to Exactor
            $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()->buildExactorProcessingService($merchantSettings->getMerchantID(),
                $merchantSettings->getUserID());
            $calculatedTax = 0;
            try {
                $exactorResponse = $exactorProcessingService->calculateTax($invoiceRequest);
                if ($exactorResponse->hasErrors()) { // Exactor unable to calculate tax
                    $msg = self::MSG_GENERAL_ERROR . 'EX' . $exactorResponse->getFirstError()->getErrorCode();
                    if (in_array($exactorResponse->getFirstError()->getErrorCode(), $this->notifyUserErrorCodes))
                        $msg = $exactorResponse->getFirstError()->getErrorDescription();
                    return $this->processTaxCalculationFail($msg);
                } else {
                    $invoiceResponse = $exactorResponse->getFirstInvoice();
                    if ($invoiceResponse != null) {
                        $calculatedTax = $invoiceResponse->getTotalTaxAmount();
                        $this->applyTaxForItems($address, $invoiceResponse);

                        if ($config->get(EXACTOR_CONFIG_ALWAYS_OVERRIDE_TAX) === true) {
                            $this->session->setLastInvoiceResponse(serialize($invoiceResponse));
                        }
                    }
                }
            } catch (Exception $e) { // Critical Exactor communication error - Network timeout for instance
                $this->applyTax(0);
                $this->resetTaxForItems($address);
                $this->logger->error(self::LOG_MESSAGE_TAX_CALC_FAILED . $e->getMessage(), 'collect');
                $this->session->addError($e->getMessage());
            }
            $this->applyTax($calculatedTax);
            $address->setTaxAmount($calculatedTax);
            $address->setBaseTaxAmount($calculatedTax);
        } else {
            if ($config->get(EXACTOR_CONFIG_ALWAYS_OVERRIDE_TAX) === true) {
                $this->processTaxOverridingRequired($address);
            } else {
                $this->processCalculationIsNotRequired($address);
            }
        }
        return $this;
    }


    /**
     * Returns true is request is just tax estimation
     * @return bool
     */
    private function isEstimation()
    {
        return strpos($this->session->getLastUrl(), "estimatePost") != false
        || strpos($this->session->getLastUrl(), "estimateUpdatePost") != false;
    }

    /**
     * Set tax amount for each item
     * @param \Mage_Sales_Model_Quote_Address $address
     * @param InvoiceResponseType $invoice
     * @return void
     */
    private function applyTaxForItems(Mage_Sales_Model_Quote_Address $address, InvoiceResponseType $invoice)
    {
        $i = 0;
        /**
         * @var Mage_Sales_Model_Quote_Item $item
         */
        foreach ($address->getAllItems() as $item) {
            $taxResultItem = $invoice->getItemById($this->exactorMappingHelper->buildExactorItemId($item));
            // If there is no item in response - set tax to 0
            if ($taxResultItem == null) {
                $item->setTaxAmount(0);
                $item->setBaseTaxAmount(0);
                $item->setTaxPercent(0);
            } else { // Else - Apply tax to the item
                $item->setTaxAmount($taxResultItem->getTotalTaxAmount());
                $item->setBaseTaxAmount($taxResultItem->getTotalTaxAmount());
                $this->applyTaxPercent($item, $taxResultItem);
            }
        }
        // Set Shipping + handling tax
        $totalShippingTax = 0;
        $shippingTaxItem = $invoice->getItemById(ZzzzzExactor_Tax_Helper_Mapping::LINE_ITEM_ID_SHIPPING);
        $handlingTaxItem = $invoice->getItemById(ZzzzzExactor_Tax_Helper_Mapping::LINE_ITEM_ID_HANDLING);
        if ($shippingTaxItem != null) $totalShippingTax += $shippingTaxItem->getTotalTaxAmount();
        if ($handlingTaxItem != null) $totalShippingTax += $handlingTaxItem->getTotalTaxAmount();
        $address->setShippingTaxAmount($totalShippingTax);
        $address->setBaseShippingTaxAmount($totalShippingTax);

        if (Mage::helper('core')->isModuleEnabled('Fooman_Surcharge')) {
            $foomanSurchargeTaxItem = $invoice->getItemById(ZzzzzExactor_Tax_Helper_Mapping::LINE_ITEM_ID_FOOMAN_SURCHARGE);
            $foomanSurchargeTax = $foomanSurchargeTaxItem != null ? $foomanSurchargeTaxItem->getTotalTaxAmount() : 0;
            $address->setFoomanSurchargeTaxAmount($foomanSurchargeTax);
            $address->setBaseFoomanSurchargeTaxAmount($foomanSurchargeTax);
            $this->session->setFoomanSurchargeTaxAmount($foomanSurchargeTax);
        }
    }

    /**
     * @param Mage_Sales_Model_Quote_Item $quoteItem
     * @param ResponseLineItem $resultItem
     */
    private function applyTaxPercent($quoteItem, $resultItem)
    {
        $taxPercent = 0;
        foreach ($resultItem->getTaxInfo() as $taxInfo) {
            $taxPercent += $taxInfo->getTaxRate();
        }
        $taxPercent *= 100;
        $taxPercent = (float)$resultItem->getTotalTaxAmount() ? $taxPercent : 0;
        $quoteItem->setTaxPercent($taxPercent);
    }

    private function resetTaxForItems(Mage_Sales_Model_Quote_Address $address)
    {
        /**
         * @var Mage_Sales_Model_Quote_Item $item
         */
        foreach ($address->getAllItems() as $item) {
            $item->setTaxAmount(0);
            $item->setBaseTaxAmount(0);
            $item->setTaxPercent(0);
        }
        $address->setShippingTaxAmount(0);

        if (Mage::helper('core')->isModuleEnabled('Fooman_Surcharge')) {
            $address->setFoomanSurchargeTaxAmount(0);
            $address->setBaseFoomanSurchargeTaxAmount(0);
            $this->session->setFoomanSurchargeTaxAmount(0);
        }
    }

    private function applyTax($amount)
    {
        $this->_setBaseAmount($amount);
        $this->_setAmount($amount);
    }

    /**
     * Return TRUE if tax calculation IS needed, FALSE - otherwise
     * @param InvoiceRequestType $invoiceRequest
     * @param ZzzzzExactor_Core_Model_MerchantSettings $merchantSettings
     * @return bool
     */
    private function checkIfCalculationNeeded($invoiceRequest, $merchantSettings)
    {
        // Calculating digital signature for the current request
        if ($invoiceRequest == null || $invoiceRequest->getLineItems() == null) return false;
        $taxRequest = ExactorConnectionFactory::getInstance()->buildRequest($merchantSettings->getMerchantID(), $merchantSettings->getUserID());
        $taxRequest->addInvoiceRequest($invoiceRequest);
        $signatureBuilder = new ExactorDigitalSignatureBuilder();
        $signatureBuilder->setTargetObject($taxRequest);
        $signature = $signatureBuilder->buildDigitalSignature();
        // Loading previous one from the session cache
        $prviousTrnInfo = $this->exactorSessionCache->getLatestTransactionInfo($invoiceRequest->getPurchaseOrderNumber());
        if ($prviousTrnInfo == null) return true;
        if ($prviousTrnInfo->getSignature() == $signature) return false;
        return true;
    }

    private function reportError($msg)
    {
        $errObj = Mage::getSingleton('core/message')->error($msg);
        foreach ($this->session->getMessages()->getErrors() as $message) {
            if ($message->getCode() == $errObj->getCode())
                return;
        }
        $this->session->addMessage($errObj);
    }

    /**
     * Do postprocessing after failed tax calculation
     * @param $reason
     * @return ZzzzzExactor_Tax_Model_Sales_Total_Quote_Tax
     */
    private function processTaxCalculationFail($reason)
    {
        $this->applyTax(0);
        $this->logger->error(self::LOG_MESSAGE_TAX_CALC_FAILED . $reason, 'collect');
        $this->reportError($reason);
        return $this;
    }

    /**
     * Get Tax label
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->_helper->__('Tax');
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $applied = $address->getAppliedTaxes();
        $store = $address->getQuote()->getStore();
        $amount = $address->getTaxAmount();
        $area = null;
        if ($this->_config->displayCartTaxWithGrandTotal($store) && $address->getGrandTotal()) {
            $area = 'taxes';
        }

//        if (is_array($applied) && (($amount!=0) || ($this->_config->displayCartZeroTax($store)))) {
        $address->addTotal(array(
            'code' => $this->getCode(),
            'title' => $this->getLabel(),
            'full_info' => $applied ? $applied : array(),
            'value' => $amount,
            'area' => $area
        ));
        //       }

        $store = $address->getQuote()->getStore();
        /**
         * Modify subtotal
         */
        if ($this->_config->displayCartSubtotalBoth($store) || $this->_config->displayCartSubtotalInclTax($store)) {
            if ($address->getSubtotalInclTax() > 0) {
                $subtotalInclTax = $address->getSubtotalInclTax();
            } else {
                $subtotalInclTax = $address->getSubtotal() + $address->getTaxAmount() - $address->getShippingTaxAmount();
            }

            $address->addTotal(array(
                'code' => 'subtotal',
                'title' => Mage::helper('sales')->__('Subtotal'),
                'value' => $subtotalInclTax,
                'value_incl_tax' => $subtotalInclTax,
                'value_excl_tax' => $address->getSubtotal(),
            ));
        }

        return $this;
    }

    private function processTaxOverridingRequired(Mage_Sales_Model_Quote_Address $address)
    {
        $invoiceResponse = unserialize($this->session->getLastInvoiceResponse());
        if (!$invoiceResponse) return;
        $calculatedTax = $invoiceResponse->getTotalTaxAmount();
        $this->applyTaxForItems($address, $invoiceResponse);
        $this->applyTax($calculatedTax);
        $address->setTaxAmount($calculatedTax);
        $address->setBaseTaxAmount($calculatedTax);
    }

    private function processCalculationIsNotRequired(Mage_Sales_Model_Quote_Address $address)
    {
        $this->applyTax($address->getTaxAmount());
        // For some reason shipping tax is 0 on this point
        // We can't fetch it from the anywhere because we don't store any amounts,
        // Thus the only solution for us - to calculate it. It's a little bit hacky way...
        /**
         * @var Mage_Sales_Model_Quote_Item $item
         */
        $shippingTax = $address->getTaxAmount();
        foreach ($address->getAllItems() as $item) {
            $shippingTax -= $item->getTaxAmount();
        }

        if (Mage::helper('core')->isModuleEnabled('Fooman_Surcharge')) {
            $foomanSurchargeTax = $this->session->getFoomanSurchargeTaxAmount();
            $address->setFoomanSurchargeTaxAmount($foomanSurchargeTax);
            $address->setBaseFoomanSurchargeTaxAmount($foomanSurchargeTax);
            $shippingTax -= $foomanSurchargeTax;
        }

        $shippingTax = Mage::app()->getStore()->roundPrice($shippingTax);
        // The following code is workaraund for the bug in Magento 1.6.2 - Tax applied to the QuoteAddress object
        // can be missed by Magento for some reason. This issue can be reproduced in very trickily manner:
        // 1. login
        // 2. add product to the cart
        // 3. logout
        // 4. login again
        // 5. proceed to purchase
        // 6. select FREE SHIPPING
        // 7. tax will be zero in front-end, negative in fact. The reason - $address->getTaxAmount() will return 0,
        //    while line items still contains valid tax
        // IMPORTANT: Issue can be reproduced only once per session (first attempt), thus you should clear cookies before login.
        if ($shippingTax < 0) {
            $this->applyTax(-1 * $shippingTax);
            $shippingTax = 0;
        }
        //
        $address->setShippingTaxAmount($shippingTax);
        $address->setBaseShippingTaxAmount($shippingTax);
    }
}