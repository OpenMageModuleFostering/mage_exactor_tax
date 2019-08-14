<?php
/**
 * User: LOGICIFY\corvis
 * Date: 4/23/12
 * Time: 7:05 PM
 */
 
class Exactor_Core_Helper_SessionCache extends Mage_Core_Helper_Abstract{
    /**
     * @var IExactorLogger
     */
    private $logger;
    /** @var Mage_Core_Model_Session */
    private $magentoSession;

    public function __construct()
    {
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        $this->magentoSession = Mage::getSingleton('core/session');
    }

    /**
     * Returns the latest tax calculation response from the session or null if nothing to fetch
     * @param null $internalId
     * @return ExactorTransactionInfo
     */
    public function getLatestTransactionInfo($internalId=null){
        $transactionArray = $this->fetchAll();
        if ($internalId==null){
            if (count($transactionArray) > 0)
                return $transactionArray[0];
        }else{
            foreach ($transactionArray as $trn){
                if ($trn->getShoppingCartTrnId() == $internalId) return $trn;
            }
        }
        return null;
    }

    /**
     * @return ExactorTransactionInfo
     */
    public function popTransactionInfo(){
        $transactionsArray = $this->fetchAll();
        if (count($transactionsArray) == 0) return;
        $result = array_shift($transactionsArray);
        $this->magentoSession->setExactorTransactionInfoArray(serialize($transactionsArray));
        return $result;
    }

    /**
     * Remove all existing information from the session cache
     * @return void
     */
    public function clear(){
        $this->magentoSession->setExactorTransactionInfo(null);
        $this->magentoSession->setExactorTransactionInfoArray(null);
    }

    /**
     * Returns array with all transactions that are stored into the session cache at the moment
     * @return array of ExactorTransactionInfo
     */
    public function fetchAll(){
        $serializedTransactionInfo = $this->magentoSession->getExactorTransactionInfoArray();
        $transactionsArray = array();
        if ($serializedTransactionInfo != null)
            $transactionsArray = unserialize($serializedTransactionInfo);
        return $transactionsArray;
    }

    /**
     * Pushes transaction info into the session
     * @param ExactorTransactionInfo $transactionInfo
     * @return void
     */
    public function pushTransactionInfo(ExactorTransactionInfo $transactionInfo){
        $this->logger->debug('Pushing transaction ' . $transactionInfo->getExactorTrnId() . ' to the session cache', 'pushTransactionInfo');
        $serializedTransactionInfo = $this->magentoSession->getExactorTransactionInfoArray();
        $transactionsArray = array();
        if ($serializedTransactionInfo != null)
            $transactionsArray = unserialize($serializedTransactionInfo);
        array_unshift($transactionsArray, $transactionInfo);
        $this->magentoSession->setExactorTransactionInfoArray(serialize($transactionsArray));
    }
}