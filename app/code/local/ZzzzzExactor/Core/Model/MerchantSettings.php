<?php
/**
 * User: LOGICIFY\corvis
 * Date: 4/20/12
 * Time: 4:46 PM
 */

class ZzzzzExactor_Core_Model_MerchantSettings extends Mage_Core_Model_Abstract
{
    // Commit Options
    const COMMIT_ON_SALES_ORDER = "SL";
    const COMMIT_ON_INVOICE = "IN";
    const COMMIT_ON_SHIPMENT = "SP";
    const COMMIT_NEVER = "NV";
    // Sku Sources
    const SKU_SOURCE_NONE = 'NA';
    const SKU_SOURCE_SKU_FIELD = 'SK';
    const SKU_SOURCE_ATTRIBUTE_NAME = 'AN';
    const SKU_SOURCE_TAX_CLASS = 'TC';
    const SKU_SOURCE_PRODUCT_CATEGORY = 'PC';

    protected $commitOptions = array(self::COMMIT_ON_SALES_ORDER => "Sales order", self::COMMIT_ON_INVOICE =>"Invoice", self::COMMIT_ON_SHIPMENT => "Shipment", self::COMMIT_NEVER => "Never");
    protected $skuSources = array(self::SKU_SOURCE_SKU_FIELD => 'SKU Field',
                                  self::SKU_SOURCE_ATTRIBUTE_NAME => 'Attribute Set Name',
                                  self::SKU_SOURCE_TAX_CLASS => 'Tax Class',
                                  self::SKU_SOURCE_PRODUCT_CATEGORY => 'Product Category',
                                  self::SKU_SOURCE_NONE => 'None'
                            );

    protected function _construct()
    {
        $this->_init('MerchantSettings/MerchantSettings');
    }

    function getID()
    {

        return $this->getData("ID");
    }

    function setID($ID)
    {

        $this->setData("ID", $ID);
    }

    function getCommitOptionsList()
    {
        return $this->commitOptions;
    }



    function getCommitOptionDescription()
    {
        return $this->commitOptions[$this->getCommitOption()];
    }

    function getStoreViewID()
    {

        return $this->getData("StoreViewID");
    }

    function setStoreViewID($StoreViewID)
    {

        $this->setData("StoreViewID", $StoreViewID);
    }


    function getMerchantID()
    {

        return $this->getData("MerchantID");
    }


    function setMerchantID($MerchantID)
    {
        $this->setData("MerchantID", $MerchantID);
    }


    function getUserID()
    {
        return $this->getData("UserID");
    }


    function setUserID($UserID)
    {
        $this->setData("UserID", $UserID);
    }

    function getFullName()
    {
        return $this->getData("FullName");
    }


    function setFullName($FullName)
    {
        $this->setData("FullName", $FullName);
    }


    function getStreet1()
    {
        return $this->getData("Street1");
    }


    function setStreet1($Street1)
    {
        $this->setData("Street1", $Street1);
    }


    function getStreet2()
    {
        return $this->getData("Street2");
    }


    function setStreet2($Street2)
    {
        $this->setData("Street2", $Street2);
    }


    function getCity()
    {
        return $this->getData("City");
    }


    function setCity($City)
    {
        $this->setData("City", $City);
    }


    function getStateOrProvince()
    {

        return $this->getData("StateOrProvince");
    }


    function setStateOrProvince($StateOrProvince)
    {

        $this->setData("StateOrProvince", $StateOrProvince);
    }


    function getPostalCode()
    {

        return $this->getData("PostalCode");
    }


    function setPostalCode($PostalCode)
    {

        $this->setData("PostalCode", $PostalCode);
    }


    function getCountry()
    {

        return $this->getData("Country");
    }


    function setCountry($Country)
    {

        $this->setData("Country", $Country);
    }


    function isShippingIncludeHandling()
    {

        return $this->getData("ShippingCharges");
    }


    function setShippingIncludeHandling($shippingIncludeHandling)
    {

        $this->setData("ShippingCharges", $shippingIncludeHandling);
    }


    function getSourceOfSKU()
    {

        return $this->getData("SourceofSKU");
    }


    function setSourceOfSKU($SourceOfSKU)
    {

        $this->setData("SourceofSKU", $SourceOfSKU);
    }

    function getCommitOption()
    {

        return $this->getData("CommitOption");
    }

    function getEffectiveDate() {
        return $this->getData("EffectiveDate");
    }

    function setEffectiveDate($EffectiveDate){
        $this->setData("EffectiveDate", $EffectiveDate);
    }


    function setCommitOption($CommitOption)
    {
        if (!array_key_exists($CommitOption, $this->commitOptions)) {
            throw new InvalidArgumentException("Invalid value for commit option. Possible values are: "
                                               + implode(", ", array_values($this->commitOptions)));
        }
        $this->setData("CommitOption", $CommitOption);
    }

    function getExactorShippingAddress(){
        $address = new AddressType();
        $address->setCity($this->getCity());
        $address->setCountry($this->getCountry());
        $address->setFullName($this->getFullName());
        $address->setPostalCode($this->getPostalCode());
        $address->setStateOrProvince($this->getStateOrProvince());
        $address->setStreet1($this->getStreet1());
        $address->setStreet2($this->getStreet2());
        return $address;
    }

    function getExemptionsSupported()
    {
        return strlen($this->getData("EntityExemptions"))>0 && $this->getData("EntityExemptions")=='1';
    }

    function setExemptionsSupported($exemptionsSupported)
    {
        $this->setData("EntityExemptions", $exemptionsSupported);
    }

    public function getSkuSourcesList()
    {
        return $this->skuSources;
    }

    private function valueOrDefaultFromArray($array, $key, $default=null){
        if (array_key_exists($key,$array))
            return $array[$key] == null ? $default : $array[$key];
        else
            return $default;
    }

    public  function getDefaultEffectiveDate() {
        $date_array = getdate();
        $timestamp = mktime(0,0,0,$date_array['mon'],1,$date_array['year']);
        return date("Y-m-d", $timestamp);
    }

    public function populateFromArray($array){
        $this->setCity($this->valueOrDefaultFromArray($array, 'City'));
        $this->setCommitOption($this->valueOrDefaultFromArray($array, 'CommitOption'));
        $this->setCountry($this->valueOrDefaultFromArray($array, 'Country'));
        $this->setExemptionsSupported($this->valueOrDefaultFromArray($array, 'EntityExemptions'));
        $this->setFullName($this->valueOrDefaultFromArray($array, 'FullName'));
        $this->setMerchantID($this->valueOrDefaultFromArray($array, 'MerchantID'));
        $this->setUserID($this->valueOrDefaultFromArray($array, 'UserID'));
        $this->setPostalCode($this->valueOrDefaultFromArray($array, 'PostalCode'));
        $this->setShippingIncludeHandling($this->valueOrDefaultFromArray($array, 'ShippingCharges'));
        $this->setSourceOfSKU($this->valueOrDefaultFromArray($array, 'SourceofSKU'));
        $this->setStateOrProvince($this->valueOrDefaultFromArray($array, 'StateOrProvince'));
        $this->setStoreViewID($this->valueOrDefaultFromArray($array, 'StoreViewID'));
        $this->setStreet1($this->valueOrDefaultFromArray($array, 'Street1'));
        $this->setStreet2($this->valueOrDefaultFromArray($array, 'Street2'));
        $this->setEffectiveDate($this->valueOrDefaultFromArray($array, 'EffectiveDate'));
    }
}