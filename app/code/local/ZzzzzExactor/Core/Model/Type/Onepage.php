<?php

class ZzzzzExactor_Core_Model_Type_Onepage extends Mage_Checkout_Model_Type_Onepage{
    /** @var Mage_Core_Model_Session_Abstract */
    private $session;
    
    public function __construct()
    {
        parent::__construct();
        $this->session = Mage::getSingleton('core/session', array('name' => 'frontend'));
    }

    public function saveCheckoutMethod($method)
    {
        $errorResponse = $this->getErrorResponse();
        if ($errorResponse != null)
            return $errorResponse;
        return parent::saveCheckoutMethod($method);
    }

    private function getErrorResponse(){
        $messages = $this->session->getMessages();
        if (count($messages->getErrors())>0){
            $exceptionsArray = array();
            foreach ($messages->getErrors() as $error){
                $exceptionsArray[] = $error->getCode();
                unset($error);
            }
            $this->session->getMessages(true);
            return array('error' => -1, 'message' => join("\n", $exceptionsArray));
        }
        return null;
    }
 
    public function saveShipping($data, $customerAddressId)
    {
        // Cleanup existing error messages
        $this->getErrorResponse();
        return parent::saveShipping($data, $customerAddressId);
    }

    public function saveOrder()
    {
        $errorResponse = $this->getErrorResponse();
        if ($errorResponse != null)
            return $errorResponse;
        return parent::saveOrder();
    }

    public function saveShippingMethod($shippingMethod)
    {
        $errorResponse = $this->getErrorResponse();
        if ($errorResponse != null)
            return $errorResponse;
        return parent::saveShippingMethod($shippingMethod);
    }

    public function savePayment($data)
    {
        $errorResponse = $this->getErrorResponse();
        if ($errorResponse != null)
            return $errorResponse;
        return parent::savePayment($data);
    }


    public function saveBilling($data, $customerAddressId)
    {
        // Cleanup existing error messages
        $this->getErrorResponse();
        return parent::saveBilling($data, $customerAddressId);
    }


}