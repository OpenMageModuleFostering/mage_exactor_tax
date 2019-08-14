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
 * @category    Exactor
 * @package     ZzzzzExactor_ExactorSettings
 * @copyright   Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * ExactorSettings block
 *
 * @category    Exactor
 * @package     ZzzzzExactor_ExactorSettingss
 */

class ZzzzzExactor_ExactorSettings_Block_Form extends Mage_Core_Block_Template {

    //const MODEL_MERCHANT_SETTINGS = "ZzzzzExactor_Core_Model_MerchantSettings";

    private $storeViewId = 0;
    
    /** @var \ZzzzzExactor_ExactorSettings_Helper_Data */
    private $exactorSettingsHelper;

    public function __construct() {
        parent::_construct();
        $this->session = Mage::getSingleton('core/session',  array('name'=>'frontend'));
        $this->exactorSettingsHelper = Mage::helper('ExactorSettings');
    }

    public function loadData(){
        $pluginVersion = ExactorConnectionFactory::getInstance()->getPluginVersion();
        $this->addData(array(
                           'pluginVersion' => $pluginVersion,
                           'accountSettings' => $this->loadMerchantSettings()
                       ));
    }

    private function dateForView($dateStr){
        $timestamp = strtotime($dateStr);
        return date("m/d/Y", $timestamp);
    }

    /**
     * Load settings object for template
     * This method always will return MerchantSettings object even if there are no any settings in DB
     * @return void
     */
    protected function loadMerchantSettings(){
        $settings = $this->exactorSettingsHelper->loadMerchantSettingsOrEmptyObject($this->getStoreViewId());
        if (trim($settings->getEffectiveDate())==""){
            $settings->setEffectiveDate($settings->getDefaultEffectiveDate());
        }
        $settings->setEffectiveDate($this->dateForView($settings->getEffectiveDate()));
        return $settings;
    }

    public function setStoreViewId($storeViewId)
    {
        $this->storeViewId = $storeViewId;
    }

    public function getStoreViewId()
    {
        return $this->storeViewId;
    }
}