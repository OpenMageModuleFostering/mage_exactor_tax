<?php
/**
 * User: LOGICIFY\corvis
 * Date: 4/20/12
 * Time: 1:12 PM
 */
 
class ZzzzzExactor_Tax_Helper_Calculation extends Mage_Core_Helper_Abstract {

    private $logger;

    private function getLogger(){
        if ($this->logger==null)
            $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
        return $this->logger;
    }

    function __construct()
    {
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
    }

}