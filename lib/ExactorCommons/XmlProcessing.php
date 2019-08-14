<?php
/**
 * This lightweight single-class library provides an ability to marshal\un-marshal XML
 * using bindings defined in property annotations.
 * USAGE:
 * 1. Declare class that extends XmlSerializationSupport.
 * 2. Define some public properties
 * 3. Optionally define getters and setters
 * 4. Use serializeToXML() to serialize to XML string
 *    Use readFromXmlString($xml)
 * By default class and property names will be used as tag names for serialization and de-serialization.
 * Besides there is an ability to customize marshaling rules using annotations in doc-comments:
 * 1. @xmlName SomeName - SomeName will be used as Tag or Attribute name instead of property or class name.
 *                        Can be applied to properties and classes.
 * 2. @xmlAttribute -     Value will be stored as attribute. Name will be generated similar to regular nodes
 *                        Can be applied only to properties.
 * 3. @xmlSkip -          Property will not be serialized to XML. Can be applied to properties.
 *
 * User: LOGICIFY\corvis
 * Date: 4/18/12
 * Time: 5:34 PM
 */

/**
 * Class represents binding rules for a class property
 * To assign binding rules to the class use '$this' as property name.
 */
class BindingRule {
    private $serializationType = XmlSerializationSupport::SERIALIZE_TO_NODE;
    private $xmlName = null;
    private $type = null;

    function __construct($propertyName)
    {
        // Xml name is a property name by default
        $this->xmlName = $propertyName;
    }

    /**
     * Property will be serialized to the xml node.
     * Will be ignored on class level.
     * @return BindingRule
     */
    public function toXmlNode() {
        $this->serializationType = XmlSerializationSupport::SERIALIZE_TO_NODE;
        return $this;
    }

    /**
     * Property will be serialized to the xml attribute
     * Will be ignored on class level.
     * @return BindingRule
     */
    public function toXmlAttribute() {
        $this->serializationType = XmlSerializationSupport::SERIALIZE_TO_ATTRIBUTE;
        return $this;
    }

    /**
     * Property will be excluded from XML processing
     * @return BindingRule
     */
    public function skip() {
        $this->serializationType = XmlSerializationSupport::SERIALIZE_TO_NOTHING;
        return $this;
    }

    /**
     * Define custom xml name. By default property name will be used.
     * @param $name
     * @return BindingRule
     */
    public function xmlName($name) {
        $this->xmlName = $name;
        return $this;
    }

    /**
     * Defines class type associated with given property. It should be a class name or array of classes.
     * Example: objectType('LineItem'); objectType('array LineItem')
     * Will be ignored on class level.
     * @param $className
     * @return BindingRule
     */
    public function objectType($className) {
        $this->type = $className;
        return $this;
    }

    public function getSerializationType()
    {
        return $this->serializationType;
    }

    public function getXmlName()
    {
        return $this->xmlName;
    }

    public function getObjectType() {
        return $this->type;
    }
}

class XmlSerializationSupport {
    const SERIALIZE_TO_NOTHING = 0;
    const SERIALIZE_TO_NODE = 1;
    const SERIALIZE_TO_ATTRIBUTE = 2;

    const TYPE_ARRAY_MODIFIER = 'array';

    private $mappingRules = array();
    private $isBindingInitialized = false;
    private $ignoreUnknownElements = true;

    protected function getNamespace(){
        return null;
    }

    protected final function setBindingRulesFor($propertyName) {
        $rule = new BindingRule($propertyName);
        $this->mappingRules[$propertyName] = $rule;
        return $rule;
    }

    protected final function getMappingRules($propertyName) {
        if (array_key_exists($propertyName, $this->mappingRules)) {
            return $this->mappingRules[$propertyName];
        }
        $rule = new BindingRule($propertyName);
        return $rule;
    }

    /**
     * This method should be overridden by children classes to define custom binding rules.
     * Default implementation is empty.
     * Typical usage is:
     * $this->setBindingRulesFor('$this')->xmlName('MyXmlNodeName'); // Sets name for xml node of the class
     * $this->setBindingRulesFor('myProperty1')->xmlName('MyProp'); // Sets name for xml element of the myProperty1
     * $this->setBindingRulesFor('myProperty2')->xmlName('mp')  // Property 'myProperty2' will be serialized to the
     *                                         ->toAttribute();       // attribute of the class with name 'mp'
     */
    protected function defineBindingRules() {

    }

    /**Returns serialization action.
     * Node - by default
     * @param ReflectionProperty $property
     * @return bool
     */
    protected final function getSerializationType(ReflectionProperty $property){
        $serializationAction = $this->getMappingRules($property->getName())->getSerializationType();
        return $serializationAction;
    }

    /**
     * By default it will be just property name, unless special binding rule has been specified
     * @param ReflectionProperty $property
     * @return string XML Name for the property
     */
    protected function getTagNameForProperty(ReflectionProperty $property){
        return $this->getMappingRules($property->getName())->getXmlName();
    }

    /**
     * Class name by default.
     * If there is a binding rule pointed to the '$this' field, XML name from that rule will be used instead
     * @param ReflectionClass $class
     * @return string
     * @see getTagNameForProperty
     */
    protected function getTagNameForClass(ReflectionClass $class){
        $defaultName = $class->getName();
        $bindingName = $this->getMappingRules('$this')->getXmlName();
        if ($bindingName != '$this') {
            return $bindingName;
        }
        return $defaultName;
    }

    protected function getTargetClassNameForProperty($property)
    {
        $className=$this->getMappingRules($property->getName())->getObjectType();
        if ($className!=null){
            if ((substr($className, 0, strlen(self::TYPE_ARRAY_MODIFIER)) === self::TYPE_ARRAY_MODIFIER)){
                $className = substr($className,strlen(self::TYPE_ARRAY_MODIFIER));
            }
        }
        return trim($className);
    }

    protected function getIsArrayForProperty($property){
        $res = false;
        $className=$this->getMappingRules($property->getName())->getObjectType();
        if ($className != null) {
            if ((substr($className, 0, strlen(self::TYPE_ARRAY_MODIFIER)) === self::TYPE_ARRAY_MODIFIER)){
                $res = true;
            }
        }
        return $res;
    }

    /**
     * Returns true if given object supports XML serialization
     * @param $obj
     * @return bool
     */
    protected function isComplexSerializableObject($obj){
        if (is_string($obj)) return false;
        return is_subclass_of($obj, 'XmlSerializationSupport');
    }

    /**
     * Do some preprocessing before serialization, e.g. remove special characters
     * @param $value
     * @return string
     */
    protected function preprocessValue($value){
        return htmlspecialchars($value);
    }

    public function toSimpleXmlObject($simpleXmlObject=null){
        $this->initializeBindingsIfNeeded();
        $reflectionObj =  new ReflectionObject($this);
        $className = $this->getTagNameForClass($reflectionObj);
        if ($simpleXmlObject==null)
            $simpleXmlObject = new SimpleXMLElement("<$className />");
        if ($this->getNamespace()!=null){
            $simpleXmlObject->addAttribute('xmlns', $this->getNamespace());
        }
        $properties = $reflectionObj->getProperties();
        foreach ($properties as $property) {
            switch($this->getSerializationType($property)){
                case self::SERIALIZE_TO_NODE:
                    $v = $property->getValue($this);
                    if (!is_array($v)){
                        $nodeArray = array($v);
                    }else{
                        $nodeArray = $v;
                    }
                    foreach ($nodeArray as $value){
                        if ($this->isComplexSerializableObject($value)){
                            $obj = $simpleXmlObject->addChild($this->getTagNameForProperty($property));
                            $value->toSimpleXmlObject($obj);
                        }else{
                            if ($value !== null)
                                $simpleXmlObject->addChild($this->getTagNameForProperty($property), $this->preprocessValue($value));
                        }
                    }
                    break;
                case self::SERIALIZE_TO_ATTRIBUTE:
                    $simpleXmlObject->addAttribute($this->getTagNameForProperty($property), $this->preprocessValue($property->getValue($this)));
                    break;
                case self::SERIALIZE_TO_NOTHING:
                    break;
                default:
                    throw new Exception('Unsupported Serialization action for property ' . $property->getName());
            }
        }
        $simpleXmlObject->saveXML();
        return $simpleXmlObject;
    }

    public function serializeToXML(){
        $this->initializeBindingsIfNeeded();
        return $this->toSimpleXmlObject()->asXML();
    }

    public function readFromXmlString($xml){
        $this->initializeBindingsIfNeeded();
        $this->populateWithSimpleXmlObject(simplexml_load_string($xml));
    }

    /**
     * @throws Exception
     * @param string $targetClass
     * @param $value
     * @param $xmlElement
     * @return generated object
     */
    private function _deserializeComplexSimpleXMLField($targetClass, $value, $xmlElement){
        try{
            if (trim($targetClass)==='') throw new Exception();
            $obj = new $targetClass;
        }catch (Exception $e){
            if ($this->ignoreUnknownElements) {
                return null;
            } else {
                throw new Exception("Can't instantiate class '$targetClass'. Please provide type annotation for element $xmlElement");
            }
        }
        if (!$this->isComplexSerializableObject($obj))
            throw new Exception("Class ($targetClass) is not a child of XmlSerializationSupport. Element $xmlElement");
        $obj->populateWithSimpleXmlObject($value);
        return $obj;
    }

    /**
     * @param SimpleXMLElement $simpleXmlObject
     * @throws Exception
     * @return void
     */
    public function  populateWithSimpleXmlObject($simpleXmlObject){
        $this->initializeBindingsIfNeeded();
        $xmlArray = (array)$simpleXmlObject; // Array representation of the SimpleXMLElement
        $reflectionObj =  new ReflectionObject($this);
        $properties = $reflectionObj->getProperties();
        foreach ($properties as $property) {
            switch($this->getSerializationType($property)){
                case self::SERIALIZE_TO_NODE:
                    $xmlTagName = $this->getTagNameForProperty($property);
                    //if (is_a($value, 'SimpleXMLElement')){
                    if (!array_key_exists($xmlTagName, $xmlArray)){
                        // If there is no such tag we should put NULL
                        $property->setValue($this, null);
                    } else if (is_array($xmlArray[$xmlTagName])) {
                        $obj = array();
                        foreach ($xmlArray[$xmlTagName] as $value){
                            $targetClass = $this->getTargetClassNameForProperty($property);
                            $obj[] = $this->_deserializeComplexSimpleXMLField($targetClass, $value, $xmlTagName);
                        }
                        $property->setValue($this, $obj);
                    } else if ($xmlArray[$xmlTagName] instanceof SimpleXMLElement){
                        $targetClass = $this->getTargetClassNameForProperty($property);
                        $isArray = $this->getIsArrayForProperty($property);
                        if ($targetClass != ''){
                            $obj = $this->_deserializeComplexSimpleXMLField($targetClass, $xmlArray[$xmlTagName], $xmlTagName);
                            if ($isArray)
                                $obj = array($obj);
                        }else{
                            $obj = '';
                        }
                        $property->setValue($this, $obj);
                    }else{
                        $property->setValue($this, $xmlArray[$xmlTagName]);
                    }
                    break;
                case self::SERIALIZE_TO_ATTRIBUTE:
                    $attributeName = $this->getTagNameForProperty($property);
                    $value = null;
                    if (array_key_exists('@attributes', $xmlArray))
                        if (array_key_exists($attributeName, $xmlArray['@attributes']))
                            $value = $xmlArray['@attributes'][$attributeName];
                    $property->setValue($this, $value);
                    break;
                case self::SERIALIZE_TO_NOTHING:
                    break;
                default:
                    throw new Exception('Unsupported Serialization action for property ' . $property->getName());
            }
        }
    }

    private function initializeBindingsIfNeeded()
    {
        if (!$this->isBindingInitialized) {
            $this->defineBindingRules();
            $this->isBindingInitialized = true;
        }
    }
}