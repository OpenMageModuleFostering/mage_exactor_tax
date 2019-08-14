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
    /** @var Exactor_Core_Model_MerchantSettings */
    private $merchantSettings;
    /** @var ExactorProcessingService*/
    private $exactorProcessingService;
    /** @var IExactorLogger */
    private $logger;

    private function setupExactorCommonLibrary(){
        $libDir = Mage::getBaseDir("lib") . '/ExactorCommons';
        require_once($libDir . '/XmlProcessing.php');
        require_once($libDir . '/ExactorDomainObjects.php');
        require_once($libDir . '/ExactorCommons.php');
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
        // Merchant Settings
        $this->merchantSettings = $this->loadMerchantSettings();
        if ($this->merchantSettings != null)
            $this->exactorProcessingService = ExactorProcessingServiceFactory::getInstance()->buildExactorProcessingService(
                                                    $this->merchantSettings->getMerchantID(),
                                                    $this->merchantSettings->getUserID());
        else
            $this->exactorProcessingService = null;
    }

    /**
     * @return Exactor_Core_Model_MerchantSettings|null
     */
    private function loadMerchantSettings(){
        $logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        $merchantSettings = $this->exactorSettingsHelper->loadValidMerchantSettings($this->getStoreViewId());
        if ($merchantSettings == null){
            $logger->error('Settings are not populated.', 'buildExactorProcessingService');
            return null;
        }
        return $merchantSettings;
    }

    private function commitTransactionForOrder($orderId){
        try{
            $this->exactorProcessingService->commitExistingInvoiceForOrder($orderId);
        }catch (Exception $e){
            $this->logger->error("Can't commit transaction. See details above.", 'commitTransactionForOrder');
        }
    }

    private function refundTransactionForOrder($orderId){
        try{
            $this->exactorProcessingService->refundTransactionForOrder($orderId);
        }catch (Exception $e){
            $this->logger->error("Can't commit transaction. See details above.", 'commitTransactionForOrder');
        }
    }

    public function handleAllOrdersCompleted(Varien_Event_Observer $observer){
        if ($this->merchantSettings == null) return;
        if (is_array($observer->getOrders()))
            $orders = array_reverse($observer->getOrders());
        else
            $orders = array($observer->getOrder());
        foreach($orders as $order){
            $transactionInfo = $this->sessionCache->popTransactionInfo();
            if ($transactionInfo == null){
                $this->logger->error('Nothing to process. There is no transaction in the session cache', 'handleCreatedOrder');
                return;
            }
            // Update transaction info with order information
            $orderId = $order->getIncrementId();
            $transactionInfo->setShoppingCartTrnId($orderId);
            // Push latest transaction from the Session to DB
            $this->exactorProcessingService->getPluginCallback()->saveTransactionInfo($transactionInfo,$transactionInfo->getSignature());
            // if CommitOption is set up to commit on sales order - do commit the
            // latest transaction from the session storage
            if ($this->merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_SALES_ORDER){
                $this->exactorProcessingService->commitExistingInvoiceForOrder($orderId);
            }
        }
        // We need to clean the session storage here
        $this->sessionCache->clear();
    }

    /**
     * Event will be fired once new order has been created
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function handleCreatedOrder(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleCreatedOrder');
        return;
    }

    public function handleNewCreditMemo(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleNewCreditMemo');
        if ($this->merchantSettings == null) return;
        $orderId = $observer->getCreditmemo()->getOrder()->getIncrementId();
        $this->refundTransactionForOrder($orderId);
    }

    public function handleCancelOrder(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleCancelOrder');
        if ($this->merchantSettings == null) return;
        $orderId = $observer->getOrder()->getIncrementId();
        $this->refundTransactionForOrder($orderId);
    }

    public function handleNewShipment(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleNewShipment');
        if ($this->merchantSettings == null) return;
        if ($this->merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_SHIPMENT){
            $orderId = $observer->getShipment()->getOrder()->getIncrementId();
            $this->commitTransactionForOrder($orderId);
        }
    }

    public function handleNewInvoice(Varien_Event_Observer $observer){
        $this->logger->trace('called', 'handleNewInvoice');
        if ($this->merchantSettings == null) return;
        
        if ($this->merchantSettings->getCommitOption() == Exactor_Core_Model_MerchantSettings::COMMIT_ON_INVOICE){
            $orderId = $observer->getInvoice()->getOrder()->getIncrementId();
            $this->commitTransactionForOrder($orderId);
        }

    }

    public function getStoreViewId(){
        // TODO: Temporary FIX! We should get store view ID from the order
        $storeId = Mage::app()->getStore()->getId();
        return $storeId != 0 ? $storeId : Mage::app()->getDefaultStoreView()->getId();
    }
}