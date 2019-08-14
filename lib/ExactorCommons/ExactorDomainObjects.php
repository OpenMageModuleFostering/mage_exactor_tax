<?php
/**
 * Contains definitions of the bushiness objects
 * User: LOGICIFY\corvis
 * Date: 4/18/12
 * Time: 5:33 PM
 */

define('EXACTOR_DATE_FORMAT', 'Y-m-d');

function exactor_date_to_string($object){
    if ($object instanceof DateTime){
        return $object->format(EXACTOR_DATE_FORMAT);
    }else
        return $object;
}

/* ========================= SHOPPING CART OBJECTS =================== */
class ExactorTransactionInfo{
    private $shoppingCartTrnId;
    private $exactorMerchantId;
    private $exactorUserId;
    private $exactorTrnId;
    private $isCommited=false;
    private $createdDate;
    private $lastModifiedDate;
    private $signature;
    private $invoiceTrn=null;

    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
    }

    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    public function setExactorMerchantId($exactorMerchantId)
    {
        $this->exactorMerchantId = $exactorMerchantId;
    }

    public function getExactorMerchantId()
    {
        return $this->exactorMerchantId;
    }

    public function setExactorTrnId($exactorTrnId)
    {
        $this->exactorTrnId = $exactorTrnId;
    }

    public function getExactorTrnId()
    {
        return $this->exactorTrnId;
    }

    public function setExactorUserId($exactorUserId)
    {
        $this->exactorUserId = $exactorUserId;
    }

    public function getExactorUserId()
    {
        return $this->exactorUserId;
    }

    public function setIsCommited($isCommited)
    {
        $this->isCommited = $isCommited;
    }

    public function getIsCommited()
    {
        return $this->isCommited;
    }

    public function setLastModifiedDate($lastModifiedDate)
    {
        $this->lastModifiedDate = $lastModifiedDate;
    }

    public function getLastModifiedDate()
    {
        return $this->lastModifiedDate;
    }

    public function setShoppingCartTrnId($shoppingCartTrnId)
    {
        $this->shoppingCartTrnId = $shoppingCartTrnId;
    }

    public function getShoppingCartTrnId()
    {
        return $this->shoppingCartTrnId;
    }

    public function setSignature($signature)
    {
        $this->signature = $signature;
    }

    public function getSignature()
    {
        return $this->signature;
    }

    public function setInvoiceTrn($invoiceTrn)
    {
        $this->invoiceTrn = $invoiceTrn;
    }

    public function getInvoiceTrn()
    {
        return $this->invoiceTrn;
    }

}

/* ========================= COMMON OBJECTS ========================== */
class AddressType extends XmlSerializationSupport {
	public $FullName = '';
	public $Street1 = '';
	public $Street2;
	public $City = '';
	public $County;
	public $StateOrProvince = '';
	public $PostalCode ='';
	public $Country ='USA';


    public function setCity($City)
    {
        $this->City = $City;
    }

    public function getCity()
    {
        return $this->City;
    }

    public function setCountry($Country)
    {
        $this->Country = $Country;
    }

    public function getCountry()
    {
        return $this->Country;
    }

    public function setCounty($County)
    {
        $this->County = $County;
    }

    public function getCounty()
    {
        return $this->County;
    }

    public function setFullName($FullName)
    {
        $this->FullName = $FullName;
    }

    public function getFullName()
    {
        return $this->FullName;
    }

    public function setPostalCode($PostalCode)
    {
        $this->PostalCode = $PostalCode;
    }

    public function getPostalCode()
    {
        return $this->PostalCode;
    }

    public function setStateOrProvince($StateOrProvince)
    {
        $this->StateOrProvince = $StateOrProvince;
    }

    public function getStateOrProvince()
    {
        return $this->StateOrProvince;
    }

    public function setStreet1($Street1)
    {
        $this->Street1 = $Street1;
    }

    public function getStreet1()
    {
        return $this->Street1;
    }

    public function setStreet2($Street2)
    {
        $this->Street2 = $Street2;
    }

    public function getStreet2()
    {
        return $this->Street2;
    }

    /** Returns true in case if address contains all required fields
     * @return bool
     */
    public function hasData(){
        return //strlen(trim($this->getStreet1()))
              // && strlen(trim($this->getCity())) 
            strlen(trim($this->getPostalCode()));
    }
}


class LineItemType extends XmlSerializationSupport {
    public $SKU;
    public $Description;
    public $Quantity=1;
    public $GrossAmount=0;
    public $BillTo;
    public $ShipTo;
    public $ShipFrom;
    public $id;

    protected function defineBindingRules()
    {
        $this->setBindingRulesFor('id')->xmlName('id')->toXmlAttribute();
    }


    public function setBillTo(AddressType $BillTo)
    {
        $this->BillTo = $BillTo;
    }

    /**
     * @return AddressType
     */
    public function getBillTo()
    {
        return $this->BillTo;
    }

    public function setDescription($Description)
    {
        $this->Description = $Description;
    }

    public function getDescription()
    {
        return $this->Description;
    }

    public function setGrossAmount($GrossAmount)
    {
        $this->GrossAmount = $GrossAmount;
    }

    public function getGrossAmount()
    {
        return $this->GrossAmount;
    }

    public function setQuantity($Quantity)
    {
        $this->Quantity = $Quantity;
    }

    public function getQuantity()
    {
        return $this->Quantity;
    }

    public function setSKU($SKU)
    {
        $this->SKU = $SKU;
    }

    public function getSKU()
    {
        return $this->SKU;
    }

    public function setShipFrom(AddressType $ShipFrom)
    {
        $this->ShipFrom = $ShipFrom;
    }

    /**
     * @return AddressType
     */
    public function getShipFrom()
    {
        return $this->ShipFrom;
    }

    public function setShipTo(AddressType $ShipTo)
    {
        $this->ShipTo = $ShipTo;
    }

    /**
     * @return AddressType
     */
    public function getShipTo()
    {
        return $this->ShipTo;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

}


/* ========================= REQUESTS ================================*/

class InvoiceRequestType extends XmlSerializationSupport {
    public $SaleDate;
    public $PurchaseOrderNumber;
    public $CurrencyCode;
    public $TaxDirection;
    public $TaxClass;
    public $ExemptionId;
    public $BillTo;
    public $ShipTo;
    public $ShipFrom;
    public $LineItems;

    protected function defineBindingRules()
    {
        $this->setBindingRulesFor('BillTo')->objectType('AddressType');
        $this->setBindingRulesFor('ShipTo')->objectType('AddressType');
        $this->setBindingRulesFor('ShipFrom')->objectType('AddressType');
        $this->setBindingRulesFor('LineItems')->objectType('array LineItemType')->xmlName('LineItem');
    }

    /**
     * @param \AddressType $BillTo
     */
    public function setBillTo($BillTo)
    {
        $this->BillTo = $BillTo;
    }

    /**
     * @return \AddressType
     */
    public function getBillTo()
    {
        return $this->BillTo;
    }

    public function setCurrencyCode($CurrencyCode)
    {
        $this->CurrencyCode = $CurrencyCode;
    }

    public function getCurrencyCode()
    {
        return $this->CurrencyCode;
    }

    public function setExemptionId($ExemptionId)
    {
        $this->ExemptionId = $ExemptionId;
    }

    public function getExemptionId()
    {
        return $this->ExemptionId;
    }

    /**
     * @param  $LineItems
     */
    public function setLineItems($LineItems)
    {
        $this->LineItems = $LineItems;
    }

    /**
     * @return LineItemType[]
     */
    public function getLineItems()
    {
        return $this->LineItems;
    }

    public function setPurchaseOrderNumber($PurchaseOrderNumber)
    {
        $this->PurchaseOrderNumber = $PurchaseOrderNumber;
    }

    public function getPurchaseOrderNumber()
    {
        return $this->PurchaseOrderNumber;
    }

    /**
     * @param DateTime $SaleDate
     * @return void
     */
    public function setSaleDate($SaleDate)
    {
        $this->SaleDate = exactor_date_to_string($SaleDate);
    }

    public function getSaleDate()
    {
        return $this->SaleDate;
    }

    /**
     * @param \AddressType $ShipFrom
     */
    public function setShipFrom($ShipFrom)
    {
        $this->ShipFrom = $ShipFrom;
    }

    /**
     * @return \AddressType
     */
    public function getShipFrom()
    {
        return $this->ShipFrom;
    }

    /**
     * @param \AddressType $ShipTo
     */
    public function setShipTo($ShipTo)
    {
        $this->ShipTo = $ShipTo;
    }

    /**
     * @return \AddressType
     */
    public function getShipTo()
    {
        return $this->ShipTo;
    }

    public function setTaxClass($TaxClass)
    {
        $this->TaxClass = $TaxClass;
    }

    public function getTaxClass()
    {
        return $this->TaxClass;
    }

    public function setTaxDirection($TaxDirection)
    {
        $this->TaxDirection = $TaxDirection;
    }

    public function getTaxDirection()
    {
        return $this->TaxDirection;
    }

    /**
     * @param LineItemType|null $lineItem
     * @return void
     */
    public function addLineItem($lineItem){
        if ($lineItem==null) return;
        if (!is_array($this->getLineItems())) $this->setLineItems(array());
        $this->LineItems[] = $lineItem;
    }
}

class CommitRequestType extends XmlSerializationSupport {
    public $CommitDate;
    public $InvoiceNumber;
    public $InvoiceRequest;
    public $PriorTransactionId;

    protected function defineBindingRules()
    {
        $this->setBindingRulesFor('InvoiceRequest')->objectType('InvoiceRequestType');
    }

    /**
     * @param DateTime $CommitDate
     * @return void
     */
    public function setCommitDate($CommitDate)
    {
        $this->CommitDate = exactor_date_to_string($CommitDate);
    }

    public function getCommitDate()
    {
        return $this->CommitDate;
    }

    public function setInvoiceNumber($InvoiceNumber)
    {
        $this->InvoiceNumber = $InvoiceNumber;
    }

    public function getInvoiceNumber()
    {
        return $this->InvoiceNumber;
    }

    /**
     * @param \InvoiceRequestType $InvoiceRequest
     */
    public function setInvoiceRequest($InvoiceRequest)
    {
        $this->InvoiceRequest = $InvoiceRequest;
    }

    /**
     * @return \InvoiceRequestType
     */
    public function getInvoiceRequest()
    {
        return $this->InvoiceRequest;
    }

    public function setPriorTransactionId($PriorTransactionId)
    {
        $this->PriorTransactionId = $PriorTransactionId;
    }

    public function getPriorTransactionId()
    {
        return $this->PriorTransactionId;
    }
}

class RefundRequestType extends XmlSerializationSupport {
    public $RefundDate;
    public $PriorTransactionId;

    public function setPriorTransactionId($PriorTransactionId)
    {
        $this->PriorTransactionId = $PriorTransactionId;
    }

    public function getPriorTransactionId()
    {
        return $this->PriorTransactionId;
    }

    public function setRefundDate($RefundDate)
    {
        $this->RefundDate = exactor_date_to_string($RefundDate);
    }

    public function getRefundDate()
    {
        return $this->RefundDate;
    }
}

class DeleteRequestType extends XmlSerializationSupport {
    public $PriorTransactionId;

    public function setPriorTransactionId($PriorTransactionId)
    {
        $this->PriorTransactionId = $PriorTransactionId;
    }

    public function getPriorTransactionId()
    {
        return $this->PriorTransactionId;
    }
}

/* ========================= Response Types ========================== */

class TaxInfo extends XmlSerializationSupport {
    public $AuthorityLevel;
    public $AuthorityName;
    public $CityOrCountyOrDistrict;
    public $StateOrProvince;
    public $Country;
    public $TaxAmount;
    public $TaxRate;

    public function setAuthorityLevel($AuthorityLevel)
    {
        $this->AuthorityLevel = $AuthorityLevel;
    }

    public function getAuthorityLevel()
    {
        return $this->AuthorityLevel;
    }

    public function setAuthorityName($AuthorityName)
    {
        $this->AuthorityName = $AuthorityName;
    }

    public function getAuthorityName()
    {
        return $this->AuthorityName;
    }

    public function setCityOrCountyOrDistrict($CityOrCountyOrDistrict)
    {
        $this->CityOrCountyOrDistrict = $CityOrCountyOrDistrict;
    }

    public function getCityOrCountyOrDistrict()
    {
        return $this->CityOrCountyOrDistrict;
    }

    public function setCountry($Country)
    {
        $this->Country = $Country;
    }

    public function getCountry()
    {
        return $this->Country;
    }

    public function setStateOrProvince($StateOrProvince)
    {
        $this->StateOrProvince = $StateOrProvince;
    }

    public function getStateOrProvince()
    {
        return $this->StateOrProvince;
    }

    public function setTaxAmount($TaxAmount)
    {
        $this->TaxAmount = $TaxAmount;
    }

    public function getTaxAmount()
    {
        return $this->TaxAmount;
    }

    public function setTaxRate($TaxRate)
    {
        $this->TaxRate = $TaxRate;
    }

    public function getTaxRate()
    {
        return $this->TaxRate;
    }
}

class ResponseLineItem extends XmlSerializationSupport{
    public $GrossAmount;
    public $TaxDirection;
    public $TotalTaxAmount;
    public $TaxInfo;
    public $id;

    protected function defineBindingRules()
    {
        $this->setBindingRulesFor('id')->xmlName('id')->toXmlAttribute();
        $this->setBindingRulesFor('TaxInfo')->objectType('array TaxInfo')->xmlName('TaxInfo');}

    public function setGrossAmount($GrossAmount)
    {
        $this->GrossAmount = $GrossAmount;
    }

    public function getGrossAmount()
    {
        return $this->GrossAmount;
    }

    public function setTaxDirection($TaxDirection)
    {
        $this->TaxDirection = $TaxDirection;
    }

    public function getTaxDirection()
    {
        return $this->TaxDirection;
    }

    public function setTaxInfo($TaxInfo)
    {
        $this->TaxInfo = $TaxInfo;
    }

    /**
     * @return TaxInfo[]
     */
    public function getTaxInfo()
    {
        return $this->TaxInfo;
    }

    public function setTotalTaxAmount($TotalTaxAmount)
    {
        $this->TotalTaxAmount = $TotalTaxAmount;
    }

    public function getTotalTaxAmount()
    {
        return $this->TotalTaxAmount;
    }

    /**
     * @param  $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return
     */
    public function getId()
    {
        return $this->id;
    }
}

class InvoiceResponseType extends XmlSerializationSupport{
    public $TransactionId;
    public $TransactionDate;
    public $SaleDate;
    public $PurchaseOrderNumber;
    public $CurrencyCode;
    public $TaxClass;
    public $TaxDirection;
    public $ExemptionId;
    public $GrossAmount;
    public $TotalTaxAmount;
    public $TaxObligation;
    public $LineItems;

    protected function defineBindingRules()
    {
        $this->setBindingRulesFor('LineItems')->objectType('array ResponseLineItem')->xmlName('LineItem');
    }


    public function setCurrencyCode($CurrencyCode)
    {
        $this->CurrencyCode = $CurrencyCode;
    }

    public function getCurrencyCode()
    {
        return $this->CurrencyCode;
    }

    public function setExemptionId($ExemptionId)
    {
        $this->ExemptionId = $ExemptionId;
    }

    public function getExemptionId()
    {
        return $this->ExemptionId;
    }

    public function setGrossAmount($GrossAmount)
    {
        $this->GrossAmount = $GrossAmount;
    }

    public function getGrossAmount()
    {
        return $this->GrossAmount;
    }

    /**
     * @param array $LineItems
     */
    public function setLineItems($LineItems)
    {
        $this->LineItems = $LineItems;
    }

    /**
     * @return array
     */
    public function getLineItems()
    {
        return $this->LineItems;
    }

    public function setPurchaseOrderNumber($PurchaseOrderNumber)
    {
        $this->PurchaseOrderNumber = $PurchaseOrderNumber;
    }

    public function getPurchaseOrderNumber()
    {
        return $this->PurchaseOrderNumber;
    }

    public function setSaleDate($SaleDate)
    {
        $this->SaleDate = $SaleDate;
    }

    public function getSaleDate()
    {
        return $this->SaleDate;
    }

    public function setTaxClass($TaxClass)
    {
        $this->TaxClass = $TaxClass;
    }

    public function getTaxClass()
    {
        return $this->TaxClass;
    }

    public function setTaxDirection($TaxDirection)
    {
        $this->TaxDirection = $TaxDirection;
    }

    public function getTaxDirection()
    {
        return $this->TaxDirection;
    }

    public function setTaxObligation($TaxObligation)
    {
        $this->TaxObligation = $TaxObligation;
    }

    public function getTaxObligation()
    {
        return $this->TaxObligation;
    }

    public function setTotalTaxAmount($TotalTaxAmount)
    {
        $this->TotalTaxAmount = $TotalTaxAmount;
    }

    public function getTotalTaxAmount()
    {
        return $this->TotalTaxAmount;
    }

    public function setTransactionDate($TransactionDate)
    {
        $this->TransactionDate = $TransactionDate;
    }

    public function getTransactionDate()
    {
        return $this->TransactionDate;
    }

    public function setTransactionId($TransactionId)
    {
        $this->TransactionId = $TransactionId;
    }

    public function getTransactionId()
    {
        return $this->TransactionId;
    }

    /**
     * @param $id
     * @return null|ResponseLineItem
     */
    public function getItemById($id){
        /**
         * @var $item ResponseLineItem
         */
        foreach($this->getLineItems() as $item){
            if ($item->getId() == $id) return $item;
        }
        return null;
    }
}

class CommitResponseType extends XmlSerializationSupport{
    public $TransactionId;
    public $TransactionDate;
    public $CommitDate;
    public $InvoiceNumber;
    public $InvoiceResponse;
    public $PriorTransactionId;

    protected function defineBindingRules()
    {
        $this->setBindingRulesFor('InvoiceResponse')->objectType('InvoiceResponseType');
    }

    public function setCommitDate($CommitDate)
    {
        $this->CommitDate = $CommitDate;
    }

    public function getCommitDate()
    {
        return $this->CommitDate;
    }

    public function setInvoiceNumber($InvoiceNumber)
    {
        $this->InvoiceNumber = $InvoiceNumber;
    }

    public function getInvoiceNumber()
    {
        return $this->InvoiceNumber;
    }

    /**
     * @param \InvoiceResponseType $InvoiceResponse
     */
    public function setInvoiceResponse($InvoiceResponse)
    {
        $this->InvoiceResponse = $InvoiceResponse;
    }

    /**
     * @return \InvoiceResponseType
     */
    public function getInvoiceResponse()
    {
        return $this->InvoiceResponse;
    }

    public function setPriorTransactionId($PriorTransactionId)
    {
        $this->PriorTransactionId = $PriorTransactionId;
    }

    public function getPriorTransactionId()
    {
        return $this->PriorTransactionId;
    }

    public function setTransactionDate($TransactionDate)
    {
        $this->TransactionDate = $TransactionDate;
    }

    public function getTransactionDate()
    {
        return $this->TransactionDate;
    }

    public function setTransactionId($TransactionId)
    {
        $this->TransactionId = $TransactionId;
    }

    public function getTransactionId()
    {
        return $this->TransactionId;
    }
}

class RefundResponseType extends XmlSerializationSupport {
    public $TransactionId;
    public $TransactionDate;
    public $RefundDate;
    public $PriorTransactionId;

    public function setPriorTransactionId($PriorTransactionId)
    {
        $this->PriorTransactionId = $PriorTransactionId;
    }

    public function getPriorTransactionId()
    {
        return $this->PriorTransactionId;
    }

    public function setRefundDate($RefundDate)
    {
        $this->RefundDate = $RefundDate;
    }

    public function getRefundDate()
    {
        return $this->RefundDate;
    }

    public function setTransactionDate($TransactionDate)
    {
        $this->TransactionDate = $TransactionDate;
    }

    public function getTransactionDate()
    {
        return $this->TransactionDate;
    }

    public function setTransactionId($TransactionId)
    {
        $this->TransactionId = $TransactionId;
    }

    public function getTransactionId()
    {
        return $this->TransactionId;
    }
}

class DeleteResponseType extends XmlSerializationSupport {
    public $TransactionId;
    public $TransactionDate;
    public $PriorTransactionId;

    public function setPriorTransactionId($PriorTransactionId)
    {
        $this->PriorTransactionId = $PriorTransactionId;
    }

    public function getPriorTransactionId()
    {
        return $this->PriorTransactionId;
    }

    public function setTransactionDate($TransactionDate)
    {
        $this->TransactionDate = $TransactionDate;
    }

    public function getTransactionDate()
    {
        return $this->TransactionDate;
    }

    public function setTransactionId($TransactionId)
    {
        $this->TransactionId = $TransactionId;
    }

    public function getTransactionId()
    {
        return $this->TransactionId;
    }
}

class ErrorResponseType extends XmlSerializationSupport {
    const ERROR_INVALID_CURRENCY_CODE = 11;
    const ERROR_INVALID_SHIP_FROM_ADDRESS = 13;
    const ERROR_INVALID_SHIP_TO_ADDRESS = 14;
    const ERROR_MISSING_LINE_ITEMS = 15;
    const ERROR_INVALID_GROSS_AMOUNT = 17;
    const ERROR_INVALID_SKU_CODE = 16;
    const ERROR_INVALID_COMMIT_DATE = 30;
    const ERROR_INVALID_REFUND_DATE = 40;

    public $LineNumber;
    public $ColumnNumber;
    public $ErrorCode;
    public $ErrorDescription;

    public function setColumnNumber($ColumnNumber)
    {
        $this->ColumnNumber = $ColumnNumber;
    }

    public function getColumnNumber()
    {
        return $this->ColumnNumber;
    }

    public function setErrorCode($ErrorCode)
    {
        $this->ErrorCode = $ErrorCode;
    }

    public function getErrorCode()
    {
        return $this->ErrorCode;
    }

    public function setErrorDescription($ErrorDescription)
    {
        $this->ErrorDescription = $ErrorDescription;
    }

    public function getErrorDescription()
    {
        return $this->ErrorDescription;
    }

    public function setLineNumber($LineNumber)
    {
        $this->LineNumber = $LineNumber;
    }

    public function getLineNumber()
    {
        return $this->LineNumber;
    }
}
/* ========================= REQUEST\RESPONSE ======================== */

class TaxRequestType extends XmlSerializationSupport {
    protected function getNamespace()
    {
        return 'http://www.exactor.com/ns';
    }

    /**
     * @var string
     * @xmlName MerchantId
     */
    public $MerchantId;
    /**
     * @var string
     * @xml UserId
     */
    public $UserId;

    /**
     * @var string
     */
    public $PartnerId;

    /**
     * @var string
     */
    public $DigitalSignature;

    /**
     * @var array InvoiceRequestType
     * @xmlName InvoiceRequest
     */
    public $InvoiceRequests = array();

    /**
     * @var array CommitRequestType
     * @xmlName CommitRequest
     */
    public $CommitRequests = array();

    /**
     * @var array RefundRequestType
     * @xmlName RefundRequest
     */
    public $RefundRequests = array();

    /**
     * @var array DeleteRequestType
     * @xmlName DeleteRequest
     */
    public $DeleteRequests = array();

    /* =========== ATTRIBUTES ============== */

    public $pluginVersion='';

    protected function defineBindingRules()
    {
        $this->setBindingRulesFor('$this')->xmlName('TaxRequest');
        $this->setBindingRulesFor('pluginVersion')->xmlName('version')->toXmlAttribute();
        $this->setBindingRulesFor('pluginName')->xmlName('plugin')->toXmlAttribute();

        $this->setBindingRulesFor('InvoiceRequests')->objectType('array InvoiceRequestType')->xmlName('InvoiceRequest');
        $this->setBindingRulesFor('CommitRequests')->objectType('array CommitRequestType')->xmlName('CommitRequest');
        $this->setBindingRulesFor('RefundRequests')->objectType('array RefundRequestType')->xmlName('RefundRequest');
        $this->setBindingRulesFor('DeleteRequests')->objectType('array DeleteRequestType')->xmlName('DeleteRequest');
        $this->setBindingRulesFor('ErrorRequests')->objectType('array ErrorRequestType')->xmlName('ErrorRequest');
    }

    /**
     * @var string
     * @xmlName plugin
     * @xmlAttribute
     */
    public $pluginName='';


    /**
     * @param array $CommitRequests
     */
    public function setCommitRequests($CommitRequests)
    {
        $this->CommitRequests = $CommitRequests;
    }

    /**
     * @return array
     */
    public function getCommitRequests()
    {
        return $this->CommitRequests;
    }

    /**
     * @param array $DeleteRequests
     */
    public function setDeleteRequests($DeleteRequests)
    {
        $this->DeleteRequests = $DeleteRequests;
    }

    /**
     * @return array
     */
    public function getDeleteRequests()
    {
        return $this->DeleteRequests;
    }

    /**
     * @param string $DigitalSignature
     */
    public function setDigitalSignature($DigitalSignature)
    {
        $this->DigitalSignature = $DigitalSignature;
    }

    /**
     * @return string
     */
    public function getDigitalSignature()
    {
        return $this->DigitalSignature;
    }

    /**
     * @param array $InvoiceRequests
     */
    public function setInvoiceRequests($InvoiceRequests)
    {
        $this->InvoiceRequests = $InvoiceRequests;
    }

    /**
     * @return array
     */
    public function getInvoiceRequests()
    {
        return $this->InvoiceRequests;
    }

    /**
     * @param string $MerchantId
     */
    public function setMerchantId($MerchantId)
    {
        $this->MerchantId = $MerchantId;
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        return $this->MerchantId;
    }

    /**
     * @param string $PartnerId
     */
    public function setPartnerId($PartnerId)
    {
        $this->PartnerId = $PartnerId;
    }

    /**
     * @return string
     */
    public function getPartnerId()
    {
        return $this->PartnerId;
    }

    /**
     * @param array $RefundRequests
     */
    public function setRefundRequests($RefundRequests)
    {
        $this->RefundRequests = $RefundRequests;
    }

    /**
     * @return array
     */
    public function getRefundRequests()
    {
        return $this->RefundRequests;
    }

    /**
     * @param string $UserId
     */
    public function setUserId($UserId)
    {
        $this->UserId = $UserId;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->UserId;
    }

    /**
     * @param string $pluginName
     */
    public function setPluginName($pluginName)
    {
        $this->pluginName = $pluginName;
    }

    /**
     * @return string
     */
    public function getPluginName()
    {
        return $this->pluginName;
    }

    /**
     * @param string $pluginVersion
     */
    public function setPluginVersion($pluginVersion)
    {
        $this->pluginVersion = $pluginVersion;
    }

    /**
     * @return string
     */
    public function getPluginVersion()
    {
        return $this->pluginVersion;
    }

    /* ============ SHORTCUTS ================== */

    public function addInvoiceRequest(InvoiceRequestType $invoiceRequest){
        if (!is_array($this->getInvoiceRequests())) $this->setInvoiceRequests(array());
        $this->InvoiceRequests[] = $invoiceRequest;
    }

    public function addCommitRequest(CommitRequestType $commitRequest){
        if (!is_array($this->getCommitRequests())) $this->setCommitRequests(array());
        $this->CommitRequests[] = $commitRequest;
    }

    public function addRefundRequest(RefundRequestType $refundRequest){
        if (!is_array($this->getRefundRequests())) $this->setRefundRequests(array());
        $this->RefundRequests[] = $refundRequest;
    }

    public function addDeleteRequest(DeleteRequestType $deleteRequest){
        if (!is_array($this->getDeleteRequests())) $this->setDeleteRequests(array());
        $this->DeleteRequests[] = $deleteRequest;
    }
}

class TaxResponseType extends XmlSerializationSupport{
    public $MerchantId;
    public $UserId;
    public $PartnerId;
    public $InvoiceResponses;
    public $CommitResponses;
    public $RefundResponses;
    public $DeleteResponses;
    public $ErrorResponses;

    protected function defineBindingRules()
    {
        $this->setBindingRulesFor('InvoiceResponses')->objectType('array InvoiceResponseType')->xmlName('InvoiceResponse');
        $this->setBindingRulesFor('CommitResponses')->objectType('array CommitResponseType')->xmlName('CommitResponse');
        $this->setBindingRulesFor('RefundResponses')->objectType('array RefundResponseType')->xmlName('RefundResponse');
        $this->setBindingRulesFor('DeleteResponses')->objectType('array DeleteResponseType')->xmlName('DeleteResponse');
        $this->setBindingRulesFor('ErrorResponses')->objectType('array ErrorResponseType')->xmlName('ErrorResponse');

    }

    /* ******** SOME SHORTCUTS *********** */

    /**
     * True if there is at least one Error Response
     * @return bool
     */
    public function hasErrors(){
        return count($this->getErrorResponses()) > 0;
    }

    /**
     * Returns first ErrorResponse from the list or null if there are on ane errors
     * @return ErrorResponseType|null
     */
    public function getFirstError(){
        if (!$this->hasErrors()) return null;
        $errors = $this->getErrorResponses();
        return $errors[0];
    }

    /** Returns first InvoiceResponseType from the list or null if there are no any invoice responses
     * @return InvoiceResponseType|null
     */
    public function getFirstInvoice(){
        if (!count($this->getInvoiceResponses())) return null;
        $invoices = $this->getInvoiceResponses();
        return $invoices[0];
    }

    /** Returns first InvoiceResponseType from the list or null if there are no any invoice responses
     * @return InvoiceResponseType|null
     */
    public function getFirstCommit() {
        if (!count($this->getCommitResponses())) return null;
        $commits = $this->getCommitResponses();
        return $commits[0];
    }

    /* ******** GETTERS AND SETTERS *********** */

    /**
     * @param string $MerchantId
     */
    public function setMerchantId($MerchantId)
    {
        $this->MerchantId = $MerchantId;
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        return $this->MerchantId;
    }

    /**
     * @param string $PartnerId
     */
    public function setPartnerId($PartnerId)
    {
        $this->PartnerId = $PartnerId;
    }

    /**
     * @return string
     */
    public function getPartnerId()
    {
        return $this->PartnerId;
    }

    /**
     * @param string $UserId
     */
    public function setUserId($UserId)
    {
        $this->UserId = $UserId;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->UserId;
    }

    /**
     * @param array $CommitResponses
     */
    public function setCommitResponses($CommitResponses)
    {
        $this->CommitResponses = $CommitResponses;
    }

    /**
     * @return array
     */
    public function getCommitResponses()
    {
        return $this->CommitResponses;
    }

    /**
     * @param array $DeleteResponses
     */
    public function setDeleteResponses($DeleteResponses)
    {
        $this->DeleteResponses = $DeleteResponses;
    }

    /**
     * @return array
     */
    public function getDeleteResponses()
    {
        return $this->DeleteResponses;
    }

    /**
     * @param array $ErrorResponses
     */
    public function setErrorResponses($ErrorResponses)
    {
        $this->ErrorResponses = $ErrorResponses;
    }

    /**
     * @return array
     */
    public function getErrorResponses()
    {
        return $this->ErrorResponses;
    }

    /**
     * @param array $InvoiceResponses
     */
    public function setInvoiceResponses($InvoiceResponses)
    {
        $this->InvoiceResponses = $InvoiceResponses;
    }

    /**
     * @return array
     */
    public function getInvoiceResponses()
    {
        return $this->InvoiceResponses;
    }

    /**
     * @param array $RefundResponses
     */
    public function setRefundResponses($RefundResponses)
    {
        $this->RefundResponses = $RefundResponses;
    }

    /**
     * @return array
     */
    public function getRefundResponses()
    {
        return $this->RefundResponses;
    }
}

