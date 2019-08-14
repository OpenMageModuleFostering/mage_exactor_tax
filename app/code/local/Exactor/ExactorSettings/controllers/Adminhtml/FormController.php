<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Exactor
 * @package    Exactor_ExactorSettings
 * @copyright  Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Exactor Settings controller
 *
 * @category   Exactor
 * @package    Exactor_ExactorSettings
 */

class Exactor_ExactorSettings_Adminhtml_FormController extends Mage_Adminhtml_Controller_Action
{
    const MODEL_MERCHANT_SETTINGS = "Exactor_Core_Model_MerchantSettings";
    const FORM_PARAM_CONTAINER = 'exactordetailsform';

    private $EXACTOR_ADDRESS_ERROR_CODES = array(13,14);

    const MSG_SESSION_KEY_MISMATCH = 'Session key mismatch';
    const MSG_NO_DATA = 'No data';
    const MSG_ERROR_MESSAGE_PREFIX = 'User input consists of the following error(-s): </br>';
    const MSG_ERROR_COMPANY_ADDRESS = 'Invalid company address. Please correct the company address to ensure that it is set to a valid address.';
    const MSG_SETTINGS_SAVED = 'Exactor Account was successfully set up.';


    private $validationMessages = array();

    const PARAM_STORE_VIEW_ID = 'StoreViewID';

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

    public function indexAction() {
        $this->setupExactorCommonLibrary();
        try{
            $this->loadLayout();
            $storeViewId = $this->getRequest()->getParam('storeview',Mage::app()->getDefaultStoreView()->getId());
            $this->getLayout()->getBlock('ExactorSettingsForm')->setStoreViewId($storeViewId);
            // Additional Actions
            if ($this->getRequest()->getParam('action_del_settings',null) != null) {
                /** @var Exactor_ExactorSettings_Helper_Data $exactorSettingsHelper */
                $exactorSettingsHelper = Mage::helper('ExactorSettings');
                $exactorSettingsHelper->removeSettings($this->getRequest()->getParam('action_del_settings',null));
                $this->sendSuccess("Successfully cleared settings");
                return;
            }
        }catch(Exception $e){
            Mage::log("$e");
        }

        $this->renderLayout();
    }

    /**
     * Will be called before validation
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return void
     */
    protected function preprocessInput($merchantSettings){
        foreach($merchantSettings->getData() as $key => $value){
            if (is_string($value))
            $merchantSettings->setData($key, trim($value));
        }
        // Convert effective date from string or set default value
        if ($merchantSettings->getEffectiveDate()==""){
            $merchantSettings->setEffectiveDate($merchantSettings->getDefaultEffectiveDate());
        } else {
            $merchantSettings->setEffectiveDate($this->dateForDb($merchantSettings->getEffectiveDate()));
        }
    }

    private function dateForDb($dateStr){
        $timestamp = strtotime($dateStr);
        return date("Y-m-d", $timestamp);
    }



    private function sendRequestBack(){
        $storeviewId = $this->getRequest()->getParam('storeview',null);
        $postData = $this->getRequest()->getPost(self::FORM_PARAM_CONTAINER);
        if ($storeviewId == null && array_key_exists(self::PARAM_STORE_VIEW_ID, $postData)){
            $storeviewId = $postData[self::PARAM_STORE_VIEW_ID];
        }else{
            $storeviewId = Mage::app()->getDefaultStoreView()->getId();
        }
        $queryParams = array('storeview' => $storeviewId);
        $this->_redirect("*/*", $queryParams);
    }

    private function sendError($message){
        #set the error message in admin session
        Mage::getSingleton('adminhtml/session')->addError($message);
        #redirect to the previous page
        $this->sendRequestBack();
    }

    private function sendSuccess($message){
        #set the error message in admin session
        Mage::getSingleton('adminhtml/session')->addSuccess($message);
        #redirect to the previous page
        $this->sendRequestBack();
    }

    public function postAction()
    {
        $this->setupExactorCommonLibrary();
        /** @var Exactor_ExactorSettings_Helper_Data $exactorSettingsHelper */
        $exactorSettingsHelper = Mage::helper('ExactorSettings');
        $formData = null;
        // Validate if we need all needed information from client
        if($this->getRequest()->getPost(self::FORM_PARAM_CONTAINER) != null) {
			$formData = $this->getRequest()->getPost(self::FORM_PARAM_CONTAINER);
            // Check if there is an regionID
            if (array_key_exists('StateOrProvinceId',$formData)){
                $regionInfo = Mage::getModel('directory/region')->load($formData['StateOrProvinceId']);
                if ($regionInfo->hasData())
                    $formData['StateOrProvince'] = $regionInfo->getCode();
            }
		}else{
            return $this->sendError($this->__(self::MSG_NO_DATA));
        }
        if($this->getRequest()->getPost('form_key')!=Mage::getSingleton('core/session')->getFormKey()){
            return $this->sendError($this->__(self::MSG_SESSION_KEY_MISMATCH));
        }
        $merchantSettings = $exactorSettingsHelper
                ->loadMerchantSettingsOrEmptyObject($formData[self::PARAM_STORE_VIEW_ID]);
        $merchantSettings->populateFromArray($formData);
        // Do pre-processing
        $this->preprocessInput($merchantSettings);
        // Do validation
        $isInputValid = $this->validate($merchantSettings);
        // if valid - Save to DB
        if ($isInputValid) {
            $merchantSettings->save();
            return $this->sendSuccess(self::MSG_SETTINGS_SAVED);
        }else{ // else - notify error
            $errorMessage = self::MSG_ERROR_MESSAGE_PREFIX;
            $errorMessage .= join('<br/>', $this->validationMessages);
            return $this->sendError($errorMessage);
        }
    }

    /**
     * Do validation and returns true if there are no any errors,
     * Otherwise - returns false and populates $this->validationMessages array with error messages
     * @param Exactor_Core_Model_MerchantSettings $merchantSettings
     * @return bool
     */
    private function validate($merchantSettings){
        $address = $merchantSettings->getExactorShippingAddress();

        // If there are no any errors - do Exactor validation
        if (count($this->validationMessages) > 0) return false;
        // Validate via exactor
        $exactorProcessingService = ExactorProcessingServiceFactory::getInstance()
                                                      ->buildExactorProcessingService($merchantSettings->getMerchantID(),
                                                                                      $merchantSettings->getUserID());
        $exactorError = new ErrorResponseType();
        try{
            if (!$exactorProcessingService->validateSettings($address, $exactorError)){
                if (in_array($exactorError->getErrorCode(), $this->EXACTOR_ADDRESS_ERROR_CODES)){
                    $this->validationMessages[] = self::MSG_ERROR_COMPANY_ADDRESS;
                }else{
                    $this->validationMessages[] = $exactorError->getErrorDescription();
                }
            }
        }catch(Exception $ex){
             $this->validationMessages[] = $ex->getMessage();
        }
        return count($this->validationMessages) == 0;
    }
}