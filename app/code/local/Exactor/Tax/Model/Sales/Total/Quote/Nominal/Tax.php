<?php
/**
 * User: LOGICIFY\corvis
 * Date: 4/20/12
 * Time: 8:39 AM
 */
 
class Exactor_Tax_Model_Sales_Total_Quote_Nominal_Tax extends Mage_Sales_Model_Quote_Address_Total_Abstract
{

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        // Do nothing we don't need nominal tax
    }
}