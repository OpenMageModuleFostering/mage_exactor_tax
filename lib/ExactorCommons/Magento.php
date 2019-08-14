<?php
/**
 * User: LOGICIFY\corvis
 * Date: 4/20/12
 * Time: 10:39 AM
 */
 
class MagentoLogger extends IExactorLogger{
    protected function doOutput($body)
    {
        echo Mage::Log($body);
    }
}

class MagentoExactorCallback extends IExactorPluginCallback{

    private $logger;
    /**
     * @var ZzzzzExactor_Core_Helper_SessionCache
     */
    private $sessionCache;

    public function __construct(){
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        $this->sessionCache = Mage::helper('ZzzzzExactor_Core_SessionCache/');
    }

    /**
     * Search transaction info by internal transaction id in local database.
     * Should return <b>null</b> if transaction doesn't exists
     * @param $shoppingCartTrnId
     * @return ExactorTransactionInfo
     */
    function loadTransactionInfo($shoppingCartTrnId)
    {
        $this->logger->trace('called', 'loadTransactionInfo');
        /** @var ZzzzzExactor_Core_Model_ExactorTransaction $exatorTransaction  */
        $exatorTransaction = Mage::getModel('ZzzzzExactor_Core_Model_ExactorTransaction');
        $exatorTransaction = $exatorTransaction->getCollection()->addFilter("OrderID",$shoppingCartTrnId)->getFirstItem();
        if (!$exatorTransaction->hasData()) return null;
        // Populating common object with DB dat
        $transactionInfo = new ExactorTransactionInfo();
        $transactionInfo->setIsCommited($exatorTransaction->getCommited());
        $transactionInfo->setCreatedDate($exatorTransaction->getDateOfOrder());
        $transactionInfo->setExactorMerchantId($exatorTransaction->getMerchantID());
        $transactionInfo->setExactorUserId($exatorTransaction->getUserID());
        $transactionInfo->setExactorTrnId($exatorTransaction->getTransactionID());
        $transactionInfo->setShoppingCartTrnId($exatorTransaction->getOrderID());
        //$transactionInfo->setLastModifiedDate();
        //$transactionInfo->setSignature('');
        return $transactionInfo;
    }

    /**
     * Save given transaction info object in the current DB.
     * If DB already contains record with given shoppingCartTrnId it should be REPLACED on the new one.
     * @param ExactorTransactionInfo $transactionInfo
     * @param $requestKey
     * @return void
     */
    function saveTransactionInfo(ExactorTransactionInfo $transactionInfo, $requestKey)
    {
        $this->logger->trace('called', 'saveTransactionInfo');
        /** @var ZzzzzExactor_Core_Model_ExactorTransaction $exatorTransaction  */
        $exatorTransaction = Mage::getModel('ZzzzzExactor_Core_Model_ExactorTransaction');
        $exatorTransaction = $exatorTransaction->getCollection()
                ->addFilter("OrderID",$transactionInfo->getShoppingCartTrnId())->getFirstItem();
        if (!$exatorTransaction->hasData()) $exatorTransaction = Mage::getModel('ZzzzzExactor_Core_Model_ExactorTransaction');

        $transactionInfo->setSignature($requestKey);
        $exatorTransaction->setCommited($transactionInfo->getIsCommited());
        $exatorTransaction->setDateOfOrder($transactionInfo->getLastModifiedDate());
        $exatorTransaction->setMerchantID($transactionInfo->getExactorMerchantId());
        $exatorTransaction->setUserID($transactionInfo->getExactorUserId());
        $exatorTransaction->setOrderID($transactionInfo->getShoppingCartTrnId());
        $exatorTransaction->setTransactionID($transactionInfo->getExactorTrnId());

        $exatorTransaction->save();
    }

    /**
     * This method will be called for each successful tax calculation request.
     * Plugin should store the latest tax information.
     * @param $requestKey
     * @param InvoiceResponseType $invoiceResponse
     * @param InvoiceRequestType $invoiceRequest
     * @param ExactorTransactionInfo $transactionInfo
     * @return void
     */
    function onTaxCalculationSuccess($requestKey, $invoiceResponse, $invoiceRequest, $transactionInfo)
    {
        $this->logger->trace('called', 'onTaxCalculationSuccess');
        if (empty($requestKey)) return;
        $this->sessionCache->pushTransactionInfo($transactionInfo);
    }

    /**
     * This method will be called once Tax Calculation fails.
     * Plugin should use it to perform some exception handling if needed. Otherwise just leave it empty.
     * @param $requestKey
     * @param ErrorResponseType $errorResponse
     * @param InvoiceRequestType $invoiceRequest
     * @return void
     */
    function onTaxCalculationFail($requestKey, $errorResponse, $invoiceRequest)
    {
        $this->logger->trace('called', 'onTaxCalculationFail');
    }

    /**
     * @param $requestKey
     * @param CommitResponseType $commitResponse
     * @param CommitRequestType $commitRequest
     * @param ExactorTransactionInfo $transactionInfo
     * @return void
     */
    function onCommitSuccess($requestKey, $commitResponse, $commitRequest, $transactionInfo)
    {
        $this->logger->trace('called', 'onCommitSuccess');
    }

    /**
     * @param $requestKey
     * @param ErrorResponseType $errorResponse
     * @param CommitRequestType $commitRequest
     * @return void
     */
    function onCommitFail($requestKey, $errorResponse, $commitRequest)
    {
        $this->logger->trace('called', 'onCommitFail');
    }

}