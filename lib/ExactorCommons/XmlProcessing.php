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
 
class XmlSerializationSupport {

    const DOC_LINE_XML_NAME = '/@xmlName\s+([\w\d]+)\n/i';
    const DOC_LINE_XML_VAR_TYPE = '/@var\s+([\w\d]+\s*[\w\d]*)/i';
    const DOC_LINE_XML_ATTRIBUTE = '/@xmlAttribute\s*\n/i';
    const DOC_LINE_XML_SKIP = '/@xmlSkip\s*\n/i';
    const SERIALIZE_TO_NOTHING = 0;
    const SERIALIZE_TO_NODE = 1;
    const SERIALIZE_TO_ATTRIBUTE = 2;

    const TYPE_ARRAY_MODIFIER = 'array';

    protected function getNamespace(){
        return null;
    }

    /**Returns serialization action.
     * Node - by default
     * Supported doc lines: @xmlAttribute, @xmlSkip
     * @param ReflectionProperty $property
     * @return bool
     */
    protected function getSerializationType(ReflectionProperty $property){
        $serializationAction = self::SERIALIZE_TO_NODE;
        // is attribute?
        if (preg_match(self::DOC_LINE_XML_ATTRIBUTE, $property->getDocComment(), $matches)>0){
            $serializationAction = self::SERIALIZE_TO_ATTRIBUTE;
        }else if(preg_match(self::DOC_LINE_XML_SKIP, $property->getDocComment(), $matches)>0){
            $serializationAction = self::SERIALIZE_TO_NOTHING;
        }
        return $serializationAction;
    }

    /**
     * By default it will be just property name.
     * If there is a DocComment with attribute @xmlName it will be used instead
     * @param ReflectionProperty $property
     * @return string XML Name for the property
     */
    protected function getTagNameForProperty(ReflectionProperty $property){
        $name = $property->getName();
        // Trying to parse DOC line of the property to gat valid name
        if (preg_match(self::DOC_LINE_XML_NAME, $property->getDocComment(), $matches)>0){
            if (count($matches)>1)
                $name=$matches[1];
        }
        return trim($name);
    }

    /** Class name by default.
     *  If there is a DocComment with attribute @xmlName it will be used instead.
     * @param ReflectionClass $class
     * @return string
     * @see getTagNameForProperty
     */
    protected function getTagNameForClass(ReflectionClass $class){
        $name = $class->getName();
        // Trying to parse DOC line of the property to gat valid name
        if (preg_match(self::DOC_LINE_XML_NAME, $class->getDocComment(), $matches)>0){
            if (count($matches)>1)
                $name=$matches[1];
        }
        return trim($name);
    }

    protected function getTargetClassNameForProperty($property)
    {
        $className=null;
        if (preg_match(self::DOC_LINE_XML_VAR_TYPE, $property->getDocComment(), $matches)>0){
            if (count($matches)>1){
                $className=$matches[1];
                if ((substr($className, 0, strlen(self::TYPE_ARRAY_MODIFIER)) === self::TYPE_ARRAY_MODIFIER)){
                    $className = substr($className,strlen(self::TYPE_ARRAY_MODIFIER));
                }
            }
        }
        return trim($className);
    }

    protected function getIsArrayForProperty($property){
        $res = false;
        if (preg_match(self::DOC_LINE_XML_VAR_TYPE, $property->getDocComment(), $matches)>0){
            if (count($matches)>1){
                if ((substr($matches[1], 0, strlen(self::TYPE_ARRAY_MODIFIER)) === self::TYPE_ARRAY_MODIFIER)){
                    $res = true;
                }
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
     * @return void
     */
    protected function preprocessValue($value){
        return htmlspecialchars($value);
    }

    public function toSimpleXmlObject($simpleXmlObject=null){
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
        return $this->toSimpleXmlObject()->asXML();
    }

    public function readFromXmlString($xml){
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
            throw new Exception("Can't instantiate class '$targetClass'. Please provide type annotation for element $xmlElement");
        }
        if (!$this->isComplexSerializableObject($obj))
            throw new Exception("Class ($targetClass) is not a child of XmlSerializationSupport. Element $xmlElement");
        $obj->populateWithSimpleXmlObject($value);
        return $obj;
    }

    /**
     * @param SimpleXMLElement $simpleXmlObject
     * @return void
     */
    public function  populateWithSimpleXmlObject($simpleXmlObject){
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
}