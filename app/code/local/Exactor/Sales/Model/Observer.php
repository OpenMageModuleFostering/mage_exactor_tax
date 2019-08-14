<?php
/**
 * User: Dmitry Berezovsky
 * Date: 11/28/11
 * Time: 12:02 PM
 */

class Exactor_Sales_Model_Observer {

    /** @var Exactor_ExactorSettings_Helper_Data */
    private $exactorSettingsHelper;
    /** @var Exactor_Core_Helper_SessionCache */
    private $sessionCache;
    /** @var ExactorProcessingService*/
    private $exactorProcessingService;
    /**
     * @var Exactor_Tax_Helper_Mapping
     */
    private $exactorMappingHelper;

    /** @var IExactorLogger */
    private $logger;

    /**
     * TODO: REMOVE THIS COMMENTED CODE
     * This object indicates whether invoice has been already handeled in compatibility mode or not.
     * It is needed to prevent double handling of the same invoice for Magento versions which OnCreatedInvoice
     * request in multi-shipping mode.
     * Once invoice created, order increment id associated with this invoice will be pushed in this list.
     * If list contains order ID this means that we already created commited transaction for it (at list
     * in the current request cycle).
     * @var bool
     */
    private $invoiceProcessingCompatibilityList = array();

    private function setupExactorCommonLibrary(){
        $libDir = Mage::getBaseDir("lib") . '/ExactorCommons';
        require_once($libDir . '/XmlProcessing.php');
        require_once($libDir . '/ExactorDomainObjects.php');
        require_once($libDir . '/ExactorCommons.php');
        require_once($libDir . '/RegionResolver.php');
        // Magento specific definitions
        require_once($libDir . '/Magento.php');
        require_once($libDir . '/config.php');
    }

    function __construct()
    {
        $this->setupExactorCommonLibrary();
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        $this->exactorSettingsHelper = Mage::helper('ExactorSettings');
        $this->sessionCache = Mage::helper('Exactor_Core_SessionCache/');
        $this->exactorMappingHelper = Mage::helper('tax/mapping');
    }

    /**
     * @param Mage_Sales_Model_Order|null $order
     * @return Exactor_Core_Model_MerchantSettings|null
     */
    private function loadMerchantSettings($order=null){
        $logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        $storeViewId = $this->getStoreViewId($order);
        $merchantSettings = $this->exactorSettingsHelper->loadValidMerchantSettings($storeViewId);

        if ($merchantSettings == null){
            $logger->error('Settings are not populated.', 'buildExactorProcessingService');
            $this->exactorProcessingService = null;
            return null;
        }else{
            $this->exactorProcessingService = ExactorProcessingServiceFactory::getInstance()->buildExactorProcessingService(
                                                    $merchantSettings->getMerchantID(),
                                                    $merchantSettings->getUserID());
        }
        return $merchantSettings;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return null|string
     */
    private function getInvoiceIncrementId($invoice) {
        if ($invoice->getIncrementId() == null) {
            try{
                $entityType = Mage::getModel('eav/entity_type')->loadByCode("invoice");
                $invoice->setIncrementId($entityType->fetchNewIncrementId($invoice->getStoreId()));
            } catch (Exception $e) {
                $this->logger->error("Failed to get increment id ", 'getInvoiceIncrementId');
                return null;
            }
        }
        return $invoice->getIncrementId();
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return null
     */
    private function commitTransactionForInvoice($invoice, $merchantSettings) {
        try {
            if (!$this->underEffectiveDate($merchantSettings->getEffectiveDate(), $invoice->getOrder()->getCreatedAt())){
                $this->logger->info("Order " . $invoice->getOrder()->getIncrementId() ." is not under effective date. Skipping.", "commitTransactionForInvoice");
                return;
            }
            $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()
                    ->buildExactorProcessingService($merchantSettings->getMerchantID(), $merchantSettings->getUserID());
            $invoiceRequests = $this->exactorMappingHelper->buildInvoiceRequestForMagentoInvoice($invoice, $merchantSettings);
            foreach ($invoiceRequests as $invoiceRequest) {
                $config = ExactorPluginConfig::getInstance();
                if ($config->getFeatureConfig()->isFeatureEnabled(EXACTOR_CONFIG_FEATURE_TRN_FILTER)) {
                    if (!$this->exactorMappingHelper->isAllowedLocation($config->get(EXACTOR_CONFIG_TRN_FILTER), $invoiceRequest->getShipTo())) {
                        $this->logger->info('Order processing interrupted: Rejected by Exactor filter', 'commitTransactionForInvoice');
                        return null;
                    }
                }
                $exactorProcessingService->partialPayment($invoiceRequest, new DateTime(), $this->getInvoiceIncrementId($invoice));
            }
        } catch (Exception $e) {
            $this->logger->error("Can't commit transaction. See details above. :" . $e->getMessage() . $e->getTraceAsString(), 'commitTransactionForInvoice');
        }
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return null
     */
    private function refundTransactionForInvoice($invoice, $merchantSettings) {
        try {
            $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()
                    ->buildExactorProcessingService($merchantSettings->getMerchantID(), $merchantSettings->getUserID());
            if (!$this->underEffectiveDate($merchantSettings->getEffectiveDate(), $invoice->getOrder()->getCreatedAt())){
                $this->logger->info("Order " . $invoice->getOrder()->getIncrementId() ." is not under effective date. Skipping.", "refundTransactionForInvoice");
                return;
            }
            $invoiceRequests = $this->exactorMappingHelper->buildInvoiceRequestForMagentoInvoice($invoice, $merchantSettings);
            foreach ($invoiceRequests as $invoiceRequest) {
                $config = ExactorPluginConfig::getInstance();
                if ($config->getFeatureConfig()->isFeatureEnabled(EXACTOR_CONFIG_FEATURE_TRN_FILTER)) {
                    if (!$this->exactorMappingHelper->isAllowedLocation($config->get(EXACTOR_CONFIG_TRN_FILTER), $invoiceRequest->getShipTo())) {
                        $this->logger->info('Order processing interrupted: Rejected by Exactor filter', 'refundTransactionForInvoice');
                        return null;
                    }
                }
                $exactorProcessingService->partialRefund($invoiceRequest, new DateTime(), $invoice->getIncrementId());
            }
        } catch (Exception $e) {
            $this->logger->error("Can't commit transaction. See details above.", 'refundTransactionForInvoice');
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     */
    private function refundAllInvoicesForOrder($order, $merchantSettings) {
        try{
            foreach ($order->getInvoiceCollection() as $invoice){
                $this->refundTransactionForInvoice($invoice, $merchantSettings);
            }
        } catch (Exception $e) {
            $this->logger->error("Can't commit transaction. See details above.", 'refundAllInvoicesForOrder');
        }
    }

    private function refundTransactionForOrder($order, $merchantSettings){
        try{
            $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()
                    ->buildExactorProcessingService($merchantSettings->getMerchantID(), $merchantSettings->getUserID());
            $config = ExactorPluginConfig::getInstance();
            if ($config->getFeatureConfig()->isFeatureEnabled(EXACTOR_CONFIG_FEATURE_TRN_FILTER)) {
                if (!$this->exactorMappingHelper->isAllowedLocation($config->get(EXACTOR_CONFIG_TRN_FILTER), $order->getShippingAddress())) {
                    $this->logger->info('Order processing interrupted: Rejected by Exactor filter', 'refundTransactionForOrder');
                    return null;
                }
            }
            $exactorProcessingService->refundTransactionForOrder($order->getIncrementId());
        }catch (Exception $e){
            $this->logger->error("Can't commit transaction. See details above.", 'refundTransactionForOrder');
        }
    }

    /**
     * Stores order in our local DB, Commits transaction if needed.
     * Returns false if for some reason operation wasn't successful.
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    private function processFinishedOrder($order){
        $merchantSettings = $this->loadMerchantSettings($order);
        if ($merchantSettings==null) return false;
        if (!$this->underEffectiveDate($merchantSettings->getEffectiveDate(), $order->getCreatedAt())){
            $this->logger->info("Order " . $order->getIncrementId() ." is not under effective date. Skipping.",
                "processFinishedOrder");
        }
        $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()
                        ->buildExactorProcessingService($merchantSettings->getMerchantID(), $merchantSettings->getUserID());
        $transactionInfo = $this->sessionCache->popTransactionInfo();
        if ($transactionInfo == null){
            $this->logger->error('Nothing to process. There is no transaction in the session cache', 'processFinishedOrder');
            return false;
        }
        // Update transaction info with order information
        $orderId = $order->getIncrementId();
        $transactionInfo->setShoppingCartTrnId($orderId);
        // Push latest transaction from the Session to DB
        $this->exactorProcessingService->getPluginCallback()->saveTransactionInfo($transactionInfo,$transactionInfo->getSignature());
        // if CommitOption is set up to commit on sales order - do commit the
        // latest transaction from the session storage
        if ($merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_SALES_ORDER)
        {
            $this->logger->info("Commiting transaction for order $orderId - " . $transactionInfo->getExactorTrnId(), 'processFinishedOrder');
            try {
                $invoiceRequests = $this->exactorMappingHelper->buildInvoiceRequestForMagentoOrder($order, $merchantSettings);
                $taxResponse = $this->commitTransactionForOrder($order, $merchantSettings);
            } catch (Exception $e) {
                $this->logger->error("Can't commit transaction. See details above.", 'processFinishedOrder');
            }
            //$this->exactorProcessingService->commitExistingInvoiceForOrder($orderId);
        }
        return true;
    }

    /**
     * Returns TaxResponse on success, and null - otherwise.
     *
     * @param \Mage_Sales_Model_Order $order
     * @param \Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return TaxResponseType
     */
    public function commitTransactionForOrder($order, $merchantSettings) {
        $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()
                        ->buildExactorProcessingService($merchantSettings->getMerchantID(), $merchantSettings->getUserID());
        try {
            if (!$this->underEffectiveDate($merchantSettings->getEffectiveDate(), $order->getCreatedAt())){
                $this->logger->info("Order " . $order->getIncrementId() ." is not under effective date. Skipping.",
                                    "commitTransactionForOrder");
            }
            $exactorTransaction = $exactorProcessingService->loadTransactionInfoByOrderId($order->getIncrementId());
            $invoiceRequests = $this->exactorMappingHelper->buildInvoiceRequestForMagentoOrder($order, $merchantSettings);
            // Check request filter if it is enabled
            $config = ExactorPluginConfig::getInstance();
            if ($config->getFeatureConfig()->isFeatureEnabled(EXACTOR_CONFIG_FEATURE_TRN_FILTER)) {
                if (!$this->exactorMappingHelper->isAllowedLocation($config->get(EXACTOR_CONFIG_TRN_FILTER), $invoiceRequests[0]->getShipTo())) {
                    $this->logger->info('Rejected by Exactor filter. Order processing. Internal tax calculation will be used instead.', 'commitTransactionForOrder');
                    return null;
                }
            }
            if (count($invoiceRequests) == 1 && $exactorTransaction != null && !$exactorTransaction->getIsCommited()) {
                $taxResponse = $exactorProcessingService->partialPayment($invoiceRequests[0], new DateTime(), "");
                $exactorTransaction->setIsCommited(true);
                $exactorTransaction->setExactorTrnId($taxResponse->getFirstCommit()->getTransactionId());
                $exactorProcessingService->getPluginCallback()
                        ->saveTransactionInfo($exactorTransaction, $exactorTransaction->getSignature());
                return $taxResponse;
            } else {
                $this->logger->error("New order should be represented by the single exactor transaction. Is", 'processFinishedOrder');
            }
        } catch (Exception $e) {
            $this->logger->error("Can't commit transaction. See details above.", 'processFinishedOrder');
        }
        return null;
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function handleAllOrdersCompleted($observer){
        $this->logger->trace('called', 'handleAllOrdersCompleted');
        if (is_array($observer->getOrders()))
            $orders = array_reverse($observer->getOrders());
        else
            $orders = array($observer->getOrder());
        foreach($orders as $order){
            $this->processFinishedOrder($order);
        }
        // We need to clean the session storage here
        $this->sessionCache->clear();
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     */
    private function partialRefund($creditMemo){
        $merchantSettings = $this->loadMerchantSettings($creditMemo->getOrder());
        if ($merchantSettings==null) return;
        if (!$this->underEffectiveDate($merchantSettings->getEffectiveDate(), $creditMemo->getOrder()->getCreatedAt())){
            $this->logger->info("Order " . $creditMemo->getOrder()->getIncrementId() ." is not under effective date. Skipping.", "partialRefund");
            return;
        }
        $invoiceRequests = $this->exactorMappingHelper->buildInvoiceRequestsForCreditMemo($creditMemo, $merchantSettings);
        $config = ExactorPluginConfig::getInstance();
        $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()->buildExactorProcessingService($merchantSettings->getMerchantID(),
                                                                                  $merchantSettings->getUserID());
        foreach ($invoiceRequests as $invoice) {
            if ($config->getFeatureConfig()->isFeatureEnabled(EXACTOR_CONFIG_FEATURE_TRN_FILTER)) {
                if (!$this->exactorMappingHelper->isAllowedLocation($config->get(EXACTOR_CONFIG_TRN_FILTER), $invoice->getShipTo())) {
                    $this->logger->info('Order processing interrupted: Rejected by Exactor filter', 'partialRefund');
                    continue;
                }
            }
            $exactorProcessingService->partialRefund($invoice, new DateTime(), "");
        }
    }

// ====================================================================================================================
// =================================== EVENT HANDLERS =================================================================
// ====================================================================================================================

    /**
     * Event will be fired once new order has been created
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function handleCreatedOrder($observer){
        $this->logger->trace('called', 'handleCreatedOrder');
        if ($this->shouldOrderProcessingRunInCompatibilityMode($observer->getOrder())) {
            $this->logger->info('Order processing started in compatibility mode.', 'handleCreatedOrder');
            $this->processFinishedOrder($observer->getOrder());
            $this->sessionCache->clear();
        }
    }

    private function shouldOrderProcessingRunInCompatibilityMode($order) {
        if ($order->getPayment() != null &&
            strpos($order->getPayment()->getMethod(), "paypal_express") !== false){
            $this->logger->info('PayPal order detected', 'shouldOrderProcessingRunInCompatibilityMode');
            return true;
        }
        $mageVersionInfo = Mage::getVersionInfo();

        if (!method_exists('Mage', 'getEdition')) {
            return false;
        }

        $mageEdition = Mage::getEdition();
        if ($mageEdition == Mage::EDITION_ENTERPRISE && $mageVersionInfo['major']==1 && $mageVersionInfo['minor'] == 8){
            $this->logger->info('Magento Enterprise detected', 'shouldOrderProcessingRunInCompatibilityMode');
            return true;
        }
        return false;
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function handleNewCreditMemo(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleNewCreditMemo');
        $merchantSettings = $this->loadMerchantSettings($observer->getCreditmemo()->getOrder());
        if ($merchantSettings==null) return;
        // TODO: What about COMMIT on shipment
        if ($merchantSettings->getCommitOption() != Exactor_Core_Model_MerchantSettings::COMMIT_NEVER) {
            if ($merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_SHIPMENT) {
                $exactorTrnInfo = $this->exactorProcessingService->loadTransactionInfoByOrderId(
                    $observer->getCreditmemo()->getOrder()->getIncrementId());
                if ($exactorTrnInfo != null && $exactorTrnInfo->getIsCommited()){
                    try{
                        $this->refundTransactionForOrder($observer->getCreditmemo()->getOrder(), $merchantSettings);
                        $exactorTrnInfo->setIsCommited(false);
                        $this->exactorProcessingService->getPluginCallback()
                                ->saveTransactionInfo($exactorTrnInfo, $exactorTrnInfo->getSignature());
                    } catch (Exception $e) {
                        $this->logger->error("Can't refund credit memo", 'handleNewCreditMemo');
                    }
                }
            } else {
                $this->partialRefund($observer->getCreditmemo());
            }
        }
    }

    public function handleCancelOrder(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleCancelOrder');
        $merchantSettings = $this->loadMerchantSettings($observer->getPayment()->getOrder());
        if ($merchantSettings==null) return;
        if ($merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_INVOICE) {
            // Just do nothing
            //$this->refundAllInvoicesForOrder($observer->getPayment()->getOrder(), $merchantSettings);
        } else {
            $this->refundTransactionForOrder($observer->getPayment()->getOrder(), $merchantSettings);
        }
    }

    public function handleNewShipment(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleNewShipment');
        $merchantSettings = $this->loadMerchantSettings($observer->getShipment()->getOrder());
        if ($merchantSettings==null) return;
        if ($merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_SHIPMENT){
            $this->commitTransactionForOrder($observer->getShipment()->getOrder(), $merchantSettings);
        }
    }
    
    public function handleCancelInvoice(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleNewInvoice');
        $merchantSettings = $this->loadMerchantSettings($observer->getInvoice()->getOrder());
        if ($merchantSettings==null) return;
        if ($merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_INVOICE){
            $this->refundTransactionForInvoice($observer->getInvoice(), $merchantSettings);
        }
    }

    public function handleNewInvoice(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleNewInvoice');
        $merchantSettings = $this->loadMerchantSettings($observer->getInvoice()->getOrder());
        if ($merchantSettings==null) return;
        // TODO: REMOVE THIS COMMENTED CODE
        //$incrementOrderId = $observer->getInvoice()->getOrder()->getIncrementId();
        if ($merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_INVOICE
           /* && !in_array($incrementOrderId, $this->invoiceProcessingCompatibilityList)*/ ){    // If flag is set this means that invoice has been handled already in compatibility mode.
            $this->commitTransactionForInvoice($observer->getInvoice(), $merchantSettings);
            /*$this->invoiceProcessingCompatibilityList[]= $incrementOrderId;*/
        }
    }

    /**
     * Checks if target date is under effective date, false - otherwise.
     *
     * @param $effectiveDate - Effective date
     * @param $targetDate    - Date to check
     * @return boolean
     */
    private function underEffectiveDate($effectiveDate, $targetDate){
        $effectiveTimestamp = strtotime($effectiveDate);
        $targetTimestamp = strtotime('now');
        if ($targetDate != null) {
            $targetTimestamp = strtotime($targetDate);
        }
        return $targetTimestamp >= $effectiveTimestamp;
    }

    /**
     * @param Mage_Sales_Model_Order|null $order
     * @return int
     */
    public function getStoreViewId($order=null){
        $storeId = Mage::app()->getStore()->getId();
        if ($order!=null){
            $storeId = $order->getStoreId() ? $order->getStoreId() : 0;
        }
        return $storeId != 0 ? $storeId : Mage::app()->getDefaultStoreView()->getId();
    }
}