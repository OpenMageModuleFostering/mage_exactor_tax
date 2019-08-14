<?php

/**
 * This class will be used by Magento Core to collect Tax total per address
 * In magento 1.3 and earlier   Mage_Tax_Model_Sales_Total_Quote_Tax
 */
class Exactor_Tax_Model_Sales_Total_Quote_Tax extends Mage_Sales_Model_Quote_Address_Total_Abstract {//Mage_Tax_Model_Sales_Total_Quote_Tax { //{

    const MODEL_MERCHANT_SETTINGS = "Exactor_Core_Model_MerchantSettings";

    const LOG_MESSAGE_TAX_CALC_FAILED = 'Tax calculation failed due to the following reason: ';

    const MSG_GENERAL_ERROR = 'An error has occurred while processing the sales taxes for this transaction. Please contact technical support if this problem persists. Code: ';

    /**
     * @var IExactorLogger
     */
    private $logger;

    /**Show message to the buyer on errors listed below, all other Exactor errors will be displayed as General errors
     * @see self::MSG_GENERAL_ERROR
     * @var array */
    private $notifyUserErrorCodes;

    /**
     * Tax module helper
     *
     * @var Mage_Tax_Helper_Data
     */
    protected $_helper;

    /**
     * @var Exactor_Tax_Helper_Calculation
     */
    private $exactorTaxCalculation;

    /**
     * @var Exactor_Tax_Helper_Mapping
     */
    private $exactorMappingHelper;

    /** @var Mage_Core_Model_Session_Abstract */
    private $session;

    /**
     * @var Exactor_Core_Helper_SessionCache
     */
    private $exactorSessionCache;
    /**
     * Tax configuration object
     *
     * @var Mage_Tax_Model_Config
     */
    protected $_config;

    /** @var \Exactor_ExactorSettings_Helper_Data */
    private $exactorSettingsHelper;

    private function setupExactorCommonLibrary(){
        $libDir = Mage::getBaseDir("lib") . '/ExactorCommons';
        require_once($libDir . '/XmlProcessing.php');
        require_once($libDir . '/ExactorDomainObjects.php');
        require_once($libDir . '/ExactorCommons.php');
        // Magento specific definitions
        require_once($libDir . '/Magento.php');
        require_once($libDir . '/config.php');
    }

    public function __construct()
    {
        $this->setCode('tax');
        $this->_helper      = Mage::helper('tax');
        $this->_config      = Mage::getSingleton('tax/config');
        $this->setupExactorCommonLibrary();
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        $this->exactorTaxCalculation = Mage::helper('tax/calculation');
        $this->exactorMappingHelper = Mage::helper('tax/mapping');
        $this->exactorSessionCache = Mage::helper('Exactor_Core_SessionCache/');
        $this->exactorSettingsHelper = Mage::helper('ExactorSettings');
        $this->session = Mage::getSingleton('core/session', array('name' => 'frontend'));
        $this->notifyUserErrorCodes = array(ErrorResponseType::ERROR_MISSING_LINE_ITEMS,
                                            ErrorResponseType::ERROR_INVALID_SHIP_TO_ADDRESS,
                                            ErrorResponseType::ERROR_INVALID_CURRENCY_CODE);
    }


    /**
     * Load merchantSettings from the database
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Exactor_Core_Model_MerchantSettings
     */
    public function loadMerchantSettings(Mage_Sales_Model_Quote_Address $address=null){
        $storeViewId = $address->getQuote()->getStoreId();//Mage::app()->getStore()->getId();
        return $this->exactorSettingsHelper->loadValidMerchantSettings($storeViewId);
    }

    /**
     * Return True if there is multi-shipping request
     * @return bool
     */
    private function isMultishippingRequest(){
        $controller = Mage::app()->getRequest()->getControllerName();
        return (strstr("multishipping",$controller)>0);
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
        if (count($address->getAllItems())<=0) return; // Skip addresses without items
        if ($address->getId() == null) return; // Skip if there is no address
        //$this->_setAmount(0);
        $this->logger->trace('Called for address #' . $address->getId() . ' (' . $address->getAddressType() . ')','collect');
        $merchantSettings = $this->loadMerchantSettings($address);

        if ($merchantSettings == null){
            $this->applyTax(0);
            $this->logger->info(self::LOG_MESSAGE_TAX_CALC_FAILED . 'Missing or invalid Merchant Settings. Pass request to internal Mage tax calc. system', 'collect');
            $internalTaxCalculator = Mage::getSingleton("Mage_Tax_Model_Sales_Total_Quote_Tax");
            return $internalTaxCalculator->collect($address);
            //return $this->processTaxCalculationFail('Missing or invalid Merchant Settings');
        }

        // Preparing Exactor Invoice Request
        $invoiceRequest = $this->exactorMappingHelper->buildInvoiceRequestForQuoteAddress($address, $merchantSettings, $this->isMultishippingRequest());
        $this->logger->trace('Invoice ' . serialize($invoiceRequest),'collect');
        if ($invoiceRequest != null && $this->checkIfCalculationNeeded($invoiceRequest, $merchantSettings)){
            // Sending to Exactor Tax Calculation Request to Exactor
            $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()->buildExactorProcessingService($merchantSettings->getMerchantID(),
                                                                                  $merchantSettings->getUserID());
            $calculatedTax = 0;
            try{
                $exactorResponse = $exactorProcessingService->calculateTax($invoiceRequest);
                if ($exactorResponse->hasErrors()){ // Exactor unable to calculate tax
                    $msg = self::MSG_GENERAL_ERROR . 'EX' . $exactorResponse->getFirstError()->getErrorCode();
                    if (in_array($exactorResponse->getFirstError()->getErrorCode(), $this->notifyUserErrorCodes))
                        $msg = $exactorResponse->getFirstError()->getErrorDescription();
                    return $this->processTaxCalculationFail($msg);
                }else{
                    $invoiceResponse = $exactorResponse->getFirstInvoice();
                    if ($invoiceResponse!=null)
                        $calculatedTax = $invoiceResponse->getTotalTaxAmount();
                }
            }catch(Exception $e){ // Critical Exactor communication error - Network timeout for instance
                $this->applyTax(0);
                $this->logger->error(self::LOG_MESSAGE_TAX_CALC_FAILED . $e->getMessage(), 'collect');
                $this->session->addError($e->getMessage());
            }
            $this->applyTax($calculatedTax);
            $address->setTaxAmount($calculatedTax);
            $address->setBaseTaxAmount($calculatedTax);
        }else{
            $this->applyTax($address->getTaxAmount());
        }
        return $this;
    }


    private function applyTax($amount){
        $this->_setBaseAmount($amount);
        $this->_setAmount($amount);
    }

    /**
     * Return TRUE if tax calculation IS needed, FALSE - otherwise
     * @param InvoiceRequestType $invoiceRequest
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return bool
     */
    private function checkIfCalculationNeeded($invoiceRequest, $merchantSettings){
        // Calculating digital signature for the current request
        $taxRequest = ExactorConnectionFactory::getInstance()->buildRequest($merchantSettings->getMerchantID(), $merchantSettings->getUserID());
        $taxRequest->addInvoiceRequest($invoiceRequest);
        $signatureBuilder = new ExactorDigitalSignatureBuilder();
        $signatureBuilder->setTargetObject($taxRequest);
        $signature = $signatureBuilder->buildDigitalSignature();
        // Loading previous one from the session cache
        $prviousTrnInfo = $this->exactorSessionCache->getLatestTransactionInfo($invoiceRequest->getPurchaseOrderNumber());
        if ($prviousTrnInfo==null) return true;
        if ($prviousTrnInfo->getSignature() == $signature) return false;
        return true;
    }

    private function reportError($msg){
        $errObj = Mage::getSingleton('core/message')->error($msg);
        foreach ($this->session->getMessages()->getErrors() as $message){
            if ($message->getCode() == $errObj->getCode())
                return;
        }
        $this->session->addMessage($errObj);
    }
    
    /**
     * Do postprocessing after failed tax calculation
     * @param $reason
     * @return Exactor_Tax_Model_Sales_Total_Quote_Tax
     */
    private function processTaxCalculationFail($reason){
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
        $applied    = $address->getAppliedTaxes();
        $store      = $address->getQuote()->getStore();
        $amount     = $address->getTaxAmount();
        $area       = null;
        if ($this->_config->displayCartTaxWithGrandTotal($store) && $address->getGrandTotal()) {
            $area   = 'taxes';
        }

//        if (is_array($applied) && (($amount!=0) || ($this->_config->displayCartZeroTax($store)))) {
            $address->addTotal(array(
                'code'      => $this->getCode(),
                'title'     => $this->getLabel(),
                'full_info' => $applied ? $applied : array(),
                'value'     => $amount,
                'area'      => $area
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
                $subtotalInclTax = $address->getSubtotal()+$address->getTaxAmount()-$address->getShippingTaxAmount();
            }

            $address->addTotal(array(
                'code'      => 'subtotal',
                'title'     => Mage::helper('sales')->__('Subtotal'),
                'value'     => $subtotalInclTax,
                'value_incl_tax' => $subtotalInclTax,
                'value_excl_tax' => $address->getSubtotal(),
            ));
        }

        return $this;
    }
}