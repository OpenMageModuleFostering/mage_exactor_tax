<?php

/**
 * User: LOGICIFY\corvis
 * Date: 4/20/12
 * Time: 8:39 AM
 */
class ZzzzzExactor_Tax_Model_Sales_Total_Quote_Nominal_Tax extends Mage_Sales_Model_Quote_Address_Total_Abstract
{

    // Do nothing we don't need nominal tax
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $this->_setAddress($address);
        if (count($address->getAllItems()) <= 0) return; // Skip addresses without items
        if ($address->getId() == null && !trim($address->getPostcode())) return; // Skip if there is no address
        // WORKAROUND: free_shipping refers to 'baseSubtotalInclTax' field that is empty. Set it manually.
        $address->setBaseSubtotalInclTax($address->getBaseSubtotal());
    }
}