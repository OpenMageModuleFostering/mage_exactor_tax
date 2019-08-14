<?php
/**
 * Contains common Exactor classes and interfaces
 * User: LOGICIFY\corvis
 * Date: 4/18/12
 * Time: 5:33 PM
 */

abstract class IExactorLogger{

    const TRACE = 0;
    const DEBUG = 1;
    const INFO = 2;
    const ERROR = 3;

    private $logLevel = self::INFO;
    private $loggerName = 'root';
    protected abstract function doOutput($body);

    public function log($message, $callee=null, $level=self::DEBUG){

        // Check if we should just skip this message on our Log Level
        if ($level < $this->getLogLevel()) return;

        if ($callee==null)
            $callee = '';
        $time = new DateTime();
        $this->doOutput('[' . $time->format('Y-m-d H:i:s') . '] ' . $this->getLoggerName() . ' - ' . $callee . ': ' . $message . "\n") ;
    }

    public function debug($msg, $callee=null){
        $this->log($msg, $callee, self::DEBUG);
    }

    public function trace($msg, $callee=null){
        $this->log($msg, $callee, self::TRACE);
    }

    public function info($msg, $callee=null){
        $this->log($msg, $callee, self::INFO);
    }

    public function error($msg, $callee=null){
        $this->log($msg, $callee, self::ERROR);
    }

    public function setLogLevel($logLevel)
    {
        $this->logLevel = $logLevel;
    }

    public function getLogLevel()
    {
        return $this->logLevel;
    }

    public function setLoggerName($loggerName)
    {
        if (!is_string($loggerName)){ // This is not class name
            $loggerName = get_class($loggerName); // Get class name
        }
        $this->loggerName = $loggerName;
    }

    public function getLoggerName()
    {
        return $this->loggerName;
    }
}

class ExactorFakeLogger extends IExactorLogger{
    protected function doOutput($body)
    {
        // Just do nothing
    }

}

class ExactorLoggingFactory{
    private static $instance;

    private $loggerClass='ExactorFakeLogger';
    private $logLevel=IExactorLogger::INFO;
    private function __construct()
    {
    }

    /**
     * @param string|clazz $name
     * @return IExactorLogger
     */
    public function getLogger($name = 'root'){
        $logger = $this->createLoggerInstance();
        $logger->setLoggerName($name);
        $logger->setLogLevel($this->logLevel);
        return $logger;
    }

    /**
     * @return IExactorLogger
     */
    private function createLoggerInstance(){
        return new $this->loggerClass;
    }

    /**
     * @static
     * @return ExactorLoggingFactory
     */
    public static function getInstance(){
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            self::$instance = new $className;
        }
        return self::$instance;
    }

    /**
     * Shortcut for settings up logging subsystem
     * @param $loggerClass
     * @param int $logLevel
     * @return void
     */
    public function setup($loggerClass, $logLevel=IExactorLogger::INFO){
        $this->loggerClass = $loggerClass;
        $this->logLevel = $logLevel;
    }

    public function setLoggerClass($loggerClass)
    {
        $this->loggerClass = $loggerClass;
    }

    public function getLoggerClass()
    {
        return $this->loggerClass;
    }

    public function setLogLevel($logLevel)
    {
        $this->logLevel = $logLevel;
    }

    public function getLogLevel()
    {
        return $this->logLevel;
    }
}

/* ******************************************************************/

class ConfigHolder {
    protected $_config;
    public function __construct() {
        $this->_config = array();
    }

    public function get($property, $default=null){
        if (array_key_exists($property, $this->_config)){
            return $this->_config[$property];
        }
    }

    public function __sleep()
    {
        return array('_config');
    }

    public function set($property, $value){
        $this->_config[$property]=$value;
    }
}

class FeatureConfigHolder extends ConfigHolder {
    public function __construct(){
        parent::__construct();
    }

    public function enableFeature($featureName) {
        $this->set('enable-' . $featureName, "true");
    }

    public function disableFeature($featureName) {
        $this->set('enable-' . $featureName, "false");
    }

    public function isFeatureEnabled($featureName) {
        return $this->get('enable-' . $featureName) == "true";
    }
}


class FeatureConfigManager {
    private $encriptionKey='';

    /**
     * Decodes config string and returns an object.
     *
     * @param $configString
     * @return FeatureConfigHolder
     */
    public function readConfigFromString($configString) {
        $obj = unserialize(base64_decode(preg_replace("/[\\s\\n]+/","",$configString)));
        if ($obj == false)
            return new FeatureConfigHolder();
        else
            return $obj;
    }

    /**
     * Returns config encoded as string
     * 
     * @param FeatureConfigHolder $config
     * @return string
     */
    public function encodeConfig(FeatureConfigHolder $config) {
        $serialized = serialize($config);
        return base64_encode($serialized);
    }
}

class ExactorPluginConfig extends ConfigHolder {
    private static $_instance;
    private $featureConfig;

    /**
     * @static
     * @return ExactorMagentoConfig
     */
    public static function getInstance(){
        if (!isset(self::$_instance)) {
            $className = __CLASS__;
            self::$_instance = new $className;
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->config = array();
        $this->featureConfig = new FeatureConfigHolder();
    }

    public function getFeatureConfig() {
        return $this->featureConfig;
    }

    public function pushFeatureConfigString($configString) {
        $configManager = new FeatureConfigManager();
        $config = $configManager->readConfigFromString($configString);
        if ($config) {
            $this->featureConfig = $config;
        }
    }
}

/* ******************** EXACTOR CONNECTOR ***************************/

class ExactorConnector {

    private $endpointUrl='';

    const HTTP_TIMEOUT = 30;
    private $logger;
    private $pluginIdentity='';

    function __construct()
    {
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
    }


    private function prepareHttpClient(){
        $client = curl_init();
		curl_setopt($client, CURLOPT_URL, $this->endpointUrl);
		curl_setopt($client, CURLOPT_POST, 1);
		curl_setopt($client, CURLOPT_HEADER, false);
        curl_setopt($client, CURLOPT_HTTPHEADER, array('Accept: application/xml', 'Content-Type: application/xml; charset=utf-8'));
        curl_setopt($client, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($client, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($client, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($client, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT);
        curl_setopt($client, CURLOPT_USERAGENT, $this->getPluginIdentity());
        return $client;
    }

    private function doHttpRequest($client, $postData){
        curl_setopt($client, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($client);
        if ($response==false){
            throw new Exception("Http communication exception. Exactor Server didn't respond in time.");
        }else if (curl_getinfo($client, CURLINFO_HTTP_CODE)!=200){
            $statusCode = curl_getinfo($client, CURLINFO_HTTP_CODE);
            $this->logger->error("Http communication exception. Server respond with status $statusCode. Response $response");
            throw new Exception("Http communication exception. Server respond with status $statusCode");
        }
        return $response;
    }

    private function destroyHttpClient($client){
        curl_close($client);
    }

    /**
     * @param TaxRequestType $taxRequest
     * @return TaxResponseType
     */
    public function sendRequest(TaxRequestType $taxRequest){
        // Serialize object to XML
        $xml = $taxRequest->toSimpleXmlObject()->asXML();
        $httpClient = $this->prepareHttpClient();
        $this->logger->info('Sending HTTP request to Exactor','sendRequest');
        $this->logger->debug('Request Content: ' . $xml,'sendRequest');
        $result = $this->doHttpRequest($httpClient, $xml);
        $this->logger->info('Sent successfully','sendRequest');
        $this->logger->debug('Response Content: ' . $result,'sendRequest');
        $this->destroyHttpClient($httpClient); // TODO: move in finally block
        $taxResponse = new TaxResponseType();
        $taxResponse->populateWithSimpleXmlObject(simplexml_load_string($result));
        return $taxResponse;
    }

    public function setEndpointUrl($endpointUrl)
    {
        $this->endpointUrl = $endpointUrl;
    }

    public function getEndpointUrl()
    {
        return $this->endpointUrl;
    }

    public function setPluginIdentity($pluginIdentity)
    {
        $this->pluginIdentity = $pluginIdentity;
    }

    public function getPluginIdentity()
    {
        return $this->pluginIdentity;
    }

}

class ExactorConnectionFactory{
    const EXACTOR_PRODUCTION_ENDPOINT = 'http://taxrequest.exactor.com/request/xml';
    private static $instance;

    private $pluginVersion=null;
    private $pluginName=null;
    private $endpointUrl=self::EXACTOR_PRODUCTION_ENDPOINT;

    private function __construct()
    {
    }

    /**
     * @static
     * @return ExactorConnectionFactory
     */
    public static function getInstance(){
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            self::$instance = new $className;
        }
        return self::$instance;
    }

    /**
     * Creates TaxRequestType and populates it with plugin and merchant info
     * @param $merchantId
     * @param $userId
     * @param null $partnerId
     * @return TaxRequestType
     */
    public function buildRequest($merchantId, $userId, $partnerId=null){
        $request = new TaxRequestType();
        $request->setMerchantId($merchantId);
        $request->setUserId($userId);
        $request->setPartnerId($partnerId);
        $request->setPluginName($this->pluginName);
        $request->setPluginVersion($this->pluginVersion);
        return $request;
    }

    /**
     * Creates Exactor connector pre-populated with endpoint url
     * @return ExactorConnector
     */
    public function buildExactorConnector(){
        $connector = new ExactorConnector();
        $connector->setEndpointUrl($this->endpointUrl);
        $connector->setPluginIdentity($this->getPluginName() .'-'.$this->getPluginVersion());
        return $connector;
    }

    /**Shortcut for setting factory properties
     * @param $pluginName
     * @param $pluginVersion
     * @param string $endPointUrl
     * @return void
     */
    public function setup($pluginName, $pluginVersion, $endPointUrl=self::EXACTOR_PRODUCTION_ENDPOINT){
        $this->endpointUrl = $endPointUrl;
        $this->pluginName = $pluginName;
        $this->pluginVersion = $pluginVersion;
    }

    public function getEndpointUrl()
    {
        return $this->endpointUrl;
    }

    public function getPluginName()
    {
        return $this->pluginName;
    }

    public function getPluginVersion()
    {
        return $this->pluginVersion;
    }
}

abstract class IExactorPluginCallback{

    /**
     * Search transaction info by internal transaction id in local database.
     * Should return <b>null</b> if transaction doesn't exists
     * @abstract
     * @param $shoppingCartTrnId
     * @return ExactorTransactionInfo
     */
    abstract function loadTransactionInfo($shoppingCartTrnId);

    /**
     * Save given transaction info object in the current DB.
     * If DB already contains record with given shoppingCartTrnId it should be REPLACED on the new one.
     * @abstract
     * @param ExactorTransactionInfo $transactionInfo
     * @param $requestKey
     * @return void
     */
    abstract function saveTransactionInfo(ExactorTransactionInfo $transactionInfo, $requestKey);

    /**
     * This method will be called for each successful tax calculation request.
     * Plugin should store the latest tax information.
     * @abstract
     * @param $requestKey
     * @param InvoiceResponseType $invoiceResponse
     * @param InvoiceRequestType $invoiceRequest
     * @param ExactorTransactionInfo $transactionInfo
     * @return void
     */
    abstract function onTaxCalculationSuccess($requestKey, $invoiceResponse, $invoiceRequest, $transactionInfo);

    /**
     * This method will be called once Tax Calculation fails.
     * Plugin should use it to perform some exception handling if needed. Otherwise just leave it empty.
     * @abstract
     * @param $requestKey
     * @param ErrorResponseType $errorResponse
     * @param InvoiceRequestType $invoiceRequest
     * @return void
     */
    abstract function onTaxCalculationFail($requestKey, $errorResponse, $invoiceRequest);

    /**
     * @abstract
     * @param $requestKey
     * @param CommitResponseType $commitResponse
     * @param CommitRequestType $commitRequest
     * @param ExactorTransactionInfo $transactionInfo
     * @return void
     */
    abstract function onCommitSuccess($requestKey, $commitResponse, $commitRequest, $transactionInfo);

    /**
     * @abstract
     * @param $requestKey
     * @param ErrorResponseType $errorResponse
     * @param CommitRequestType $commitRequest
     * @return void
     */
    abstract function onCommitFail($requestKey, $errorResponse, $commitRequest);
}

/**
 * Class should be used to generate Digital signature
 * Usage:
 * <pre>
 * signatureBuilder = new ExactorDigitalSignatureBuilder();
 * signatureBuilder->setTargetObject($taxRequest);
 * signatureBuilder->buildSignatureSource(); // To get source string sequence that
 *                                           // can be used for generation of the digital signature
 * signatureBuilder->buildDigitalSignature(); // To get digest
 * </pre>
 */
class ExactorDigitalSignatureBuilder{

    /**
     * @var TaxRequestType
     */
    private $targetObject;
    private $signatureFieldList=array();

    private function appendValue($value){
        if ($value==null) $value='';
        $str = str_replace("\n", ' ', $value);
        $this->signatureFieldList[] = $str;
    }

    /**
     * @param AddressType $address
     * @return void
     */
    private function appendAddressFields($address){
        if ($address == null) return;
        //$this->appendValue($address->getFullName());
        $this->appendValue($address->getStreet1());
        $this->appendValue($address->getStreet2());
        $this->appendValue($address->getCity());
        $this->appendValue($address->getCounty());
        $this->appendValue($address->getStateOrProvince());
        $this->appendValue($address->getPostalCode());
        $this->appendValue($address->getCountry());
    }

    private function appendInvoiceFields($invoice){
        if ($invoice == null) return;
        $this->appendValue($invoice->getSaleDate());
        $this->appendValue($invoice->getExemptionId());
        $this->appendAddressFields($invoice->getShipTo());
        $this->appendAddressFields($invoice->getShipFrom());
        if ($invoice->getLineItems() == null) return;
        foreach ($invoice->getLineItems() as $lineItem){
            $this->appendValue($lineItem->getId());
            $this->appendValue($lineItem->getSKU());
            $this->appendValue($lineItem->getDescription());
            $this->appendValue($lineItem->getQuantity());
            $this->appendValue($lineItem->getGrossAmount());
            $this->appendAddressFields($lineItem->getShipFrom());
            $this->appendAddressFields($lineItem->getBillTo());
            $this->appendAddressFields($lineItem->getShipTo());
        }
    }

    public function buildSignatureSource(){
        $this->signatureFieldList = array();
        // Building field list
        $this->appendValue($this->targetObject->getMerchantId());
        $this->appendValue($this->targetObject->getUserId());
        foreach ($this->targetObject->getInvoiceRequests() as $invoice){
            $this->appendInvoiceFields($invoice);
        }
        foreach ($this->targetObject->getCommitRequests() as $commit){
            $this->appendValue($commit->getCommitDate());
            $this->appendValue($commit->getPriorTransactionId());
            $this->appendValue($commit->getInvoiceRequest);
        }
        foreach ($this->targetObject->getRefundRequests() as $refund){
            $this->appendValue($refund->getRefundDate());
            $this->appendValue($commit->getPriorTransactionId());
        }
        foreach ($this->targetObject->getDeleteRequests() as $delete){
            $this->appendValue($delete->getPriorTransactionId());
        }
        $result = join("\n", $this->signatureFieldList);
        return $result;
    }

    public function buildDigitalSignature(){
        $res = $this->buildSignatureSource();
        return hash('sha256', $res);
    }

    /**
     * @param \TaxRequestType $targetObject
     */
    public function setTargetObject($targetObject)
    {
        $this->targetObject = $targetObject;
    }

    /**
     * @return \TaxRequestType
     */
    public function getTargetObject()
    {
        return $this->targetObject;
    }
}

class ExactorProcessingService{
    const MSG_EXACTOR_ACCOUNT_VERIFICATION = 'Exactor Account Verification. Plugin Version ';
    const MSG_TEST_TRANSACTION_CAPTION = 'Test Transaction';
    /**
     * @var IExactorPluginCallback
     */
    private $pluginCallback;
    private $merchantId;
    private $userId;
    private $partnerId;
    private $logger;

    public function __construct()
    {
        $this->logger = ExactorLoggingFactory::getInstance()->getLogger($this);
    }

    /**
     * Applies address fallback logic(if needed).
     * IMPORTANT: This method should be invoked AFTER all available address information was set in invoice obj.
     *
     * @param InvoiceRequestType $invoice
     * @return void
     */
    public function applyAddressFallbackLogic($invoice) {
        if ($invoice->getBillTo() == null && $invoice->getShipTo() == null) {
            return;
        }
        if ($invoice->getShipTo() == null || !$invoice->getShipTo()->hasData()) {
            $invoice->setShipTo($invoice->getBillTo());
        }
    }

    /**
     * Fires following events:
     * -> onTaxCalculationFail
     * -> saveTransactionInfo - if calculation was successful and there is order number
     * -> onTaxCalculationSuccess - if calculation was successful
     * @param $invoiceRequest
     * @return TaxResponseType
     */
    public function calculateTax($invoiceRequest){
        $this->logger->trace('Composing and sending request with one Invoice','calculateTax');
        $request = ExactorConnectionFactory::getInstance()->buildRequest($this->merchantId, $this->userId, $this->partnerId);
        $this->applyAddressFallbackLogic($invoiceRequest);
        $request->addInvoiceRequest($invoiceRequest);
        // Create signature
        $signatureBuilder = new ExactorDigitalSignatureBuilder();
        $signatureBuilder->setTargetObject($request);
        $signature = $signatureBuilder->buildDigitalSignature();
        $response = ExactorConnectionFactory::getInstance()->buildExactorConnector()->sendRequest($request);
        // Do callback to the plugin
        if ($response->hasErrors()){
            $this->logger->error('Exactor Tax Calculation request failed. See debug info for details');
            $this->pluginCallback->onTaxCalculationFail($signature, $response->getFirstError(), $invoiceRequest);
        }else{
            $invoice = $response->getFirstInvoice();
            $transactionInfo = $this->createExactorTransactionInfoForInvoiceResponse($response, $signature);
            $this->pluginCallback->onTaxCalculationSuccess($signature, $invoice, $invoiceRequest, $transactionInfo);
            if ($invoice->getPurchaseOrderNumber() != null)
                $this->pluginCallback->saveTransactionInfo($transactionInfo, $signature);
        }
        return $response;
    }

    /**
     * @param AddressType $address
     * @param \ErrorResponseType $errorObject
     * @internal param $validationMessage
     * @return bool
     */
    public function validateSettings($address, $errorObject){
        $exactorConnector = ExactorConnectionFactory::getInstance();
        $request = ExactorConnectionFactory::getInstance()->buildRequest($this->merchantId, $this->userId, $this->partnerId);
        $invoiceRequest = new InvoiceRequestType();
        $invoiceRequest->setBillTo($address);
        $invoiceRequest->getBillTo()->setFullName($exactorConnector->getPluginName() . ' ' . self::MSG_TEST_TRANSACTION_CAPTION);
        $invoiceRequest->setShipFrom($address);
        $invoiceRequest->setShipTo($address);
        $invoiceRequest->setSaleDate(new DateTime());
        $lineItem = new LineItemType();
        $lineItem->setDescription(self::MSG_EXACTOR_ACCOUNT_VERIFICATION . $exactorConnector->getPluginVersion());
        $lineItem->setGrossAmount(0);
        $lineItem->setId('_0');
        $lineItem->setSKU('');
        $invoiceRequest->addLineItem($lineItem);
        $request->addInvoiceRequest($invoiceRequest);
        $response = ExactorConnectionFactory::getInstance()->buildExactorConnector()->sendRequest($request);
        if ($response->hasErrors()){
            $errorObject = $response->getFirstError();
            return false;
        }else{
            return true;
        }
    }

    /**
     * @param TaxResponseType $taxResponse
     * @param $signature
     * @return ExactorTransactionInfo
     */
    private function createExactorTransactionInfoForInvoiceResponse($taxResponse, $signature){
        $invoiceResponse = $taxResponse->getFirstInvoice();
        $exactorResponse = new ExactorTransactionInfo();
        $exactorResponse->setCreatedDate($invoiceResponse->getTransactionDate());
        $exactorResponse->setExactorMerchantId($taxResponse->getMerchantId());
        $exactorResponse->setExactorUserId($taxResponse->getUserId());
        $exactorResponse->setExactorTrnId($invoiceResponse->getTransactionId());
        $exactorResponse->setShoppingCartTrnId($invoiceResponse->getPurchaseOrderNumber());
        $exactorResponse->setIsCommited(false);
        $exactorResponse->setSignature($signature);
        $exactorResponse->setShoppingCartTrnId($taxResponse->getFirstInvoice()->getPurchaseOrderNumber());
        return $exactorResponse;
    }

    private function buildDateTimeFromString($dateString){
        return new DateTime($dateString);
    }

    /**
     * @param InvoiceResponseType|CommitResponseType $response
     * @param $orderNumber
     * @return ExactorTransactionInfo
     */
    private function buildTransactionInfo($response, $orderNumber=null){
        $transactionInfo = new ExactorTransactionInfo();
        $transactionInfo->setCreatedDate(new DateTime());
        $transactionInfo->setExactorMerchantId($this->merchantId);
        $transactionInfo->setExactorUserId($this->userId);
        $transactionInfo->setExactorTrnId($response->getTransactionId());
        if ($orderNumber==null && ($response instanceof CommitResponseType))
            $transactionInfo->setShoppingCartTrnId($response->getInvoiceNumber());
        else{
            $transactionInfo->setShoppingCartTrnId($orderNumber);
        }
        if ($response instanceof CommitResponseType){
            $transactionInfo->setLastModifiedDate($response->getCommitDate());
        }else{
            $transactionInfo->setLastModifiedDate(new DateTime());
        }
        $transactionInfo->setIsCommited($response instanceof CommitResponseType);
        return $transactionInfo;
    }

    /**
     * Fetch information about transaction from the internal database by given internal id and send commit request.
     * Fires following events:
     *  -> onCommitFail
     *  -> saveTransactionInfo – after success commit to notify plugin that
     *                           the existing transaction info  should be updated in the internal database.
     *  -> onCommitSuccess – after the previous one in case when commit was successful.
     * Throws Exception in case when there is no transaction with given internal ID or it is already commited.
     * @throws Exception
     * @param $orderNumber
     * @return TaxResponseType
     */
    public function commitExistingInvoiceForOrder($orderNumber){
        $this->logger->trace('Composing and sending request with one Commit(by existing invoice)','commitExistingInvoiceForOrder');
        $transactionInfo = $this->pluginCallback->loadTransactionInfo($orderNumber);
        if ($transactionInfo==null){
            $this->logger->error("Can't load transaction info for order #" . $orderNumber, 'commitExistingInvoiceForOrder' );
            throw new Exception("Can't load transaction info for order #" . $orderNumber);
        }
        if ($transactionInfo->getIsCommited()){
            $this->logger->error("Transaction for order #" . $orderNumber . " is already marked as COMMITED",'commitExistingInvoiceForOrder');
            throw new Exception("Transaction for order #" . $orderNumber . " is already marked as COMMITED");
        }
        $request = ExactorConnectionFactory::getInstance()->buildRequest($this->merchantId, $this->userId, $this->partnerId);
        $commitRequest = new CommitRequestType();
        $commitRequest->setInvoiceNumber($orderNumber);
        $commitRequest->setCommitDate($this->buildDateTimeFromString($transactionInfo->getLastModifiedDate()));
        $commitRequest->setPriorTransactionId($transactionInfo->getExactorTrnId());
        $request->addCommitRequest($commitRequest);
        $signature = '';
        $response = ExactorConnectionFactory::getInstance()->buildExactorConnector()->sendRequest($request);
        // Do callback to the plugin
        if ($response->hasErrors()){
            if ($response->getFirstError()->getErrorCode() == ErrorResponseType::ERROR_INVALID_COMMIT_DATE){
                $commitRequest->setCommitDate(new DateTime());
                $response = ExactorConnectionFactory::getInstance()->buildExactorConnector()->sendRequest($request);
                if ($response->hasErrors()){
                    $this->logger->error('Exactor Commit request failed. See debug info for details');
                    $this->pluginCallback->onCommitFail($signature, $response->getFirstError(), $commitRequest);
                }
            }else{
                $this->logger->error('Exactor Commit request failed. See debug info for details');
                $this->pluginCallback->onCommitFail($signature, $response->getFirstError(), $commitRequest);
            }
        }else{
            $commitResponses = $response->getCommitResponses();
            $trnInfo = $this->buildTransactionInfo($commitResponses[0],$orderNumber);
            $this->pluginCallback->saveTransactionInfo($trnInfo, $commitRequest);
            $this->pluginCallback->onCommitSuccess($signature, $commitResponses[0], $commitRequest, null); // TODO: pass transaction info
        }
        return $response;
    }

    /**
     * Just creates CommitRequestType and sends commit request to Exactor.
     * If failed due to the invalid date - tries to use current one.
     *
     * @param InvoiceRequestType $invoiceRequest
     * @param DateTime $date
     * @param string $invoiceNumber
     * @return TaxResponseType
     */
    private function commitNewInvoiceRequestObject(InvoiceRequestType $invoiceRequest, DateTime $date, $invoiceNumber="unknown") {
        $commitRequest = new CommitRequestType();
        $commitRequest->setCommitDate($date);
        $commitRequest->setInvoiceNumber($invoiceNumber);
        $this->applyAddressFallbackLogic($invoiceRequest);
        $commitRequest->setInvoiceRequest($invoiceRequest);
        $request = ExactorConnectionFactory::getInstance()->buildRequest($this->merchantId, $this->userId, $this->partnerId);
        $request->addCommitRequest($commitRequest);
        $response = ExactorConnectionFactory::getInstance()->buildExactorConnector()->sendRequest($request);
        $signature='';
        if ($response->hasErrors()) {
            if ($response->getFirstError()->getErrorCode() == ErrorResponseType::ERROR_INVALID_COMMIT_DATE){
                $commitRequest->setCommitDate(new DateTime());
                $response = ExactorConnectionFactory::getInstance()->buildExactorConnector()->sendRequest($request);
                if ($response->hasErrors()){
                    $this->logger->error('Exactor Commit request failed. See debug info for details');
                    $this->pluginCallback->onCommitFail($signature, $response->getFirstError(), $commitRequest);
                }
            }else{
                $this->logger->error('Exactor Commit request failed. See debug info for details');
                $this->pluginCallback->onCommitFail($signature, $response->getFirstError(), $commitRequest);
            }
        }else{
            $commitResponses = $response->getCommitResponses();
            $this->pluginCallback->onCommitSuccess($signature, $commitResponses[0], $commitRequest, null);
        }
        return $response;
    }

    /**
     * Performs commit of the given invoice object is useful for partial payments.
     * Basically just convenient method for committing transactions.
     *
     * @param InvoiceRequestType $invoiceRequest
     * @param DateTime $date
     * @param string $invoiceNumber
     * @return TaxResponseType
     */
    public function partialPayment(InvoiceRequestType $invoiceRequest, DateTime $date, $invoiceNumber="unknown") {
        return $this->commitNewInvoiceRequestObject($invoiceRequest, $date, $invoiceNumber);
    }

    /**
     * Performs partial refund basing on the InvoiceRequest information.
     * Basically it just negate all amounts in the given invoice transaction and commits it.
     * @param InvoiceRequestType $invoiceRequest
     * @param DateTime $date
     * @param string $invoiceNumber
     * @return TaxResponseType
     */
    public function partialRefund(InvoiceRequestType $invoiceRequest, DateTime $date, $invoiceNumber="unknown"){
        // Negate all item amounts
        foreach ($invoiceRequest->getLineItems() as $lineItem){
            $lineItem->setGrossAmount(-1 * $lineItem->getGrossAmount());
        }
        return $this->partialPayment($invoiceRequest, $date, $invoiceNumber);
    }

    /**
     * Fetch information about transaction from the internal database by given internal id
     * and send refund request.
     * Throws Exception in case when there is no transaction with given internal ID or it is not commited.
     * @throws Exception
     * @param $orderNumber
     * @return TaxResponseType
     */
    public function refundTransactionForOrder($orderNumber){
        $this->logger->trace('Composing and sending request with one Refund(for existing commit)','commitExistingInvoiceForOrder');
        $transactionInfo = $this->pluginCallback->loadTransactionInfo($orderNumber);
        if ($transactionInfo==null){
            $this->logger->error("Can't load transaction info for order #" . $orderNumber, 'commitExistingInvoiceForOrder' );
            throw new Exception("Can't load transaction info for order #" . $orderNumber);
        }
        if (!$transactionInfo->getIsCommited()){
            $this->logger->error("Transaction for order #" . $orderNumber . " is not COMMITED",'commitExistingInvoiceForOrder');
            throw new Exception("Transaction for order #" . $orderNumber . " is not COMMITED");
        }
        $request = ExactorConnectionFactory::getInstance()->buildRequest($this->merchantId, $this->userId, $this->partnerId);
        $refundRequest = new RefundRequestType();
        // Set Refund date to the commit date
        $refundRequest->setRefundDate($this->buildDateTimeFromString($transactionInfo->getLastModifiedDate()));
        $refundRequest->setPriorTransactionId($transactionInfo->getExactorTrnId());
        $request->addRefundRequest($refundRequest);
        $response = ExactorConnectionFactory::getInstance()->buildExactorConnector()->sendRequest($request);
        // Do callback to the plugin
        if ($response->hasErrors()){
            if ($response->getFirstError()->getErrorCode() == ErrorResponseType::ERROR_INVALID_REFUND_DATE){
                $refundRequest->setRefundDate(new DateTime());
                $response = ExactorConnectionFactory::getInstance()->buildExactorConnector()->sendRequest($request);
                if ($response->hasErrors()){
                    $this->logger->error('Exactor Tax Calculation request failed. See debug info for details');
                }
            }else{
                $this->logger->error('Exactor Tax Calculation request failed. See debug info for details');
            }
        }else{
            // Nothing to do here
        }
        return $response;
    }

    /**
     * Returns transaction info by given internal order ID. Or null if it doesn't exists.
     * @param $orderId
     * @return ExactorTransactionInfo
     */
    public function loadTransactionInfoByOrderId($orderId){
        return $this->getPluginCallback()->loadTransactionInfo($orderId);
    }

    /**
     * @param \IExactorPluginCallback $pluginCallback
     */
    public function setPluginCallback($pluginCallback)
    {
        $this->pluginCallback = $pluginCallback;
    }

    /**
     * @return \IExactorPluginCallback
     */
    public function getPluginCallback()
    {
        return $this->pluginCallback;
    }

    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;
    }

    public function getMerchantId()
    {
        return $this->merchantId;
    }

    public function setPartnerId($partnerId)
    {
        $this->partnerId = $partnerId;
    }

    public function getPartnerId()
    {
        return $this->partnerId;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function getUserId()
    {
        return $this->userId;
    }
}

class ExactorProcessingServiceFactory{
    private static $instance;

    /**
     * @var IExactorPluginCallback
     */
    private $pluginCallback;

    function __construct()
    {

    }

    /**
     * @static
     * @return ExactorProcessingServiceFactory
     */
    public static function getInstance(){
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            self::$instance = new $className;
        }
        return self::$instance;
    }

    public function setup($pluginCallback){
        $this->setPluginCallback($pluginCallback);
    }

    /**
     * Creates Exactor Processing Service for Merchant
     * @param $merchantId
     * @param $userId
     * @param null $partnerId
     * @return ExactorProcessingService
     */
    public function buildExactorProcessingService($merchantId, $userId, $partnerId=null){
        $exactorProcessingService = new ExactorProcessingService();
        $exactorProcessingService->setPluginCallback($this->getPluginCallback());
        $exactorProcessingService->setMerchantId($merchantId);
        $exactorProcessingService->setUserId($userId);
        $exactorProcessingService->setPartnerId($partnerId);
        return $exactorProcessingService;
    }

    /**
     * @param \IExactorPluginCallback $pluginCallback
     */
    public function setPluginCallback($pluginCallback)
    {
        $this->pluginCallback = $pluginCallback;
    }

    /**
     * @return \IExactorPluginCallback
     */
    public function getPluginCallback()
    {
        return $this->pluginCallback;
    }

}