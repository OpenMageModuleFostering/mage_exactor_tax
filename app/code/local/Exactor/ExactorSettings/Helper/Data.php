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
 * User: LOGICIFY\corvis
 * Date: 4/24/12
 * Time: 5:12 PM
 */
 
class Exactor_ExactorSettings_Helper_Data extends Mage_Core_Helper_Abstract{

    const MODEL_MERCHANT_SETTINGS = "Exactor_Core_Model_MerchantSettings";

    /**
     * Returns merchant settings from DB or null if there is no any record
     * @param null $storeViewId
     * @return Exactor_Core_Model_MerchantSettings|null
     */
    function loadMerchantSettings($storeViewId=null){
        $merchantSettingsModel = $this->loadMerchantSettingsOrEmptyObject($storeViewId);
        if (!$merchantSettingsModel->hasData()) return null;
        return $merchantSettingsModel;
    }

    /**
     * Returns merchant settings from DB or empty Exactor_Core_Model_MerchantSettings if there is no any record
     * @param null $storeViewId
     * @return Exactor_Core_Model_MerchantSettings|null
     */
    function loadMerchantSettingsOrEmptyObject($storeViewId=null){
        //if ($storeViewId == null) $storeViewId = 1; // 1 - ID of the default store view
        $merchantSettingsModel = Mage::getModel(self::MODEL_MERCHANT_SETTINGS);
        $merchantSettingsModel = $merchantSettingsModel
                ->getCollection()
                ->addFilter('StoreViewID', $storeViewId)
                ->getFirstItem();
        return $merchantSettingsModel;
    }

    function removeSettings($storeViewId) {
        $merchantSettingsModel = $this->loadMerchantSettingsOrEmptyObject($storeViewId);
        if ($merchantSettingsModel->getID() != null){
            $merchantSettingsModel->delete();
            return true;
        }
        return false;
    }

    /**
     * Returns merchant settings from DB
     * or null if there is <strong>no any record</strong> or settings <strong>are not populated</strong>
     * @param null $storeViewId
     * @return Exactor_Core_Model_MerchantSettings|null
     */
    public function loadValidMerchantSettings($storeViewId=null){
        return $this->loadMerchantSettings($storeViewId);
    }

    /**
     * @return void
     */
    public function updateMageConfig(){
        Mage::getConfig()->setNode('tax/calculation/based_on','shipping');
        Mage::getConfig()->setNode('tax/calculation/apply_after_discount','1');
    }
}