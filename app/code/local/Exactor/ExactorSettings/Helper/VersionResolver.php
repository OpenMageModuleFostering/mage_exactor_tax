<?php
/**
 * This class allows to resolve versions using expression language.
 * Supported notation: <version><operation>
 * Version must contain digits separated by dots e.g. 1.4, 1.4.1, 1.6.0
 * Operation can be >, <, >=, <=, =, empty(equal to =)
 * Example: 1.4.1>= - means version 1.4.1 or above
 * User: Dmitry Berezovsky
 * Date: 10/19/11
 * Time: 2:35 PM
 */
 
class Exactor_Exactordetails_Helper_VersionResolver extends Mage_Core_Helper_Abstract{
    function __construct() {
		#call the model function here
		$this->magentoVersion = Mage::getVersion();
	}

    private function versionToInt($expression){
        $versionNumber = str_replace('.','',$expression);
        $res = intval($versionNumber);
        $res *= pow(10, 4-strlen(strval($res)));
        return $res;
    }

    private function getOperation($expression){
        $result=array();
        if (preg_match('/[<>=]+$/',$expression, $result)==1){
            return $result[0];
        }
        return '=';
    }

    private function compare($operator, $val1, $val2){
        //Mage::log("compare: " . strval($val1) . ' and ' . $val2);
        switch ($operator){
            case '=':
                return $val1 == $val2;
                break;
            case '>=':
                return $val1 >= $val2;
                break;
            case '>':
                return $val1 > $val2;
                break;
            case '<=':
                return $val1 >= $val2;
                break;
            case '<':
                return $val1 < $val2;
                break;
            default:
                return false;
        }
    }

    public function selectVersion($expression, $versionList){
        $versionNumber=$this->versionToInt($expression);
        $operation = $this->getOperation($expression);
        $result = array();
        foreach($versionList as $num => $val){
            if (compare($operation, $versionNumber, $this->versionToInt($val))){
                array_push($result, $val);
            }
        }
        return $result;
    }

    public function isAllowedForVersion($expression){
        return $this->compare($this->getOperation($expression), $this->versionToInt($this->magentoVersion), $this->versionToInt($expression));
    }
    
}
