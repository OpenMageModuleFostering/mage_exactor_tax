<?php
/**
 * User: LOGICIFY\corvis
 * Date: 5/7/12
 * Time: 1:13 PM
 */

class ZzzzzExactor_ExactorSettings_AjaxController extends Mage_Core_Controller_Front_Action{
    const FIELD_EXACTOR_STATUS = "exactor_status";

    /** @var Mage_Core_Model_Session_Abstract */
    private $session;

    protected function _construct()
    {
        parent::_construct();
        $this->session = Mage::getSingleton('core/session', array('name' => 'frontend'));
    }


    public function getExactorStatusAction(){
        $jsonBody=array();
        $messageCollection = $this->session->getMessages();
        $errorsArray = $messageCollection->getErrors();
        $jsonBody[self::FIELD_EXACTOR_STATUS] = null;
        if (count($errorsArray) > 0){
            $messageCollection->clear();
            $jsonBody[self::FIELD_EXACTOR_STATUS] = $errorsArray[0]->getCode();
        }
        $this->getResponse()->setBody(json_encode($jsonBody));
    }
}

