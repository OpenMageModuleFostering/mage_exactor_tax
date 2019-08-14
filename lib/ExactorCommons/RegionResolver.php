<?php
/**
 * User: Dmitry Berezovsky
 * Date: 7/31/12
 * Time: 11:37 AM
 */

/**
 * This helper class provides methods for determining region name or abbreviation by given postal code.
 * Supports both of US and Canada postal codes.
 */
class RegionResolver {
    const REGEX_VALID_ZIP = '/^\d{5}(-\d{4})?$/i';
    // Each array contains 3 elements:
    // C_USA_STATE_ABBREVIATION(0)  - State abbreviation
    // C_USA_ZIP_LOWER_BOUND(1)     - Lower bound of the zip range
    // C_USA_ZIP_UPPER_BOUND(2)     - Upper bound of the zip range
    const C_USA_STATE_ABBREVIATION = 0;
    const C_USA_ZIP_LOWER_BOUND = 1;
    const C_USA_ZIP_UPPER_BOUND = 2;
    private $USA_CODES_TABLE = array(array("AL", 35004, 36925), array("AK", 99501, 99950), array("AZ", 85001, 86556),
                                     array("AR", 71601, 72959), array("CA", 90001, 96162), array("CO", 80001, 81658),
                                     array("CT", 6001, 6389), array("CT", 6401, 6928), array("DE", 19701, 19980),
                                     array("DC", 20001, 20098), array("DC", 20201, 20586), array("DC", 20590, 20597),
                                     array("DC", 20599, 20599), array("DC", 56901, 56972), array("FL", 32003, 33994),
                                     array("FL", 34101, 34997), array("GA", 30002, 31999), array("GA", 39813, 39901),
                                     array("HI", 96701, 96797), array("HI", 96801, 96898), array("ID", 83201, 83406),
                                     array("ID", 83415, 83877), array("IL", 60001, 62999), array("IN", 46001, 47997),
                                     array("IA", 50001, 52809), array("KS", 66002, 67954), array("KY", 40003, 42788),
                                     array("LA", 70001, 71497), array("ME", 3901, 4992), array("MD", 20588, 20588),
                                     array("MD", 20601, 21930), array("MA", 1001, 2791), array("MA", 5501, 5544),
                                     array("MI", 48001, 49971), array("MN", 55001, 56763), array("MS", 38601, 39776),
                                     array("MO", 63001, 65899), array("MT", 59001, 59937), array("NE", 68001, 69367),
                                     array("NV", 88901, 89883), array("NH", 3031, 3897), array("NJ", 7001, 8989),
                                     array("NM", 87001, 88439), array("NY", 501, 544), array("NY", 6390, 6390),
                                     array("NY", 10001, 14925), array("NC", 27006, 28909), array("ND", 58001, 58856),
                                     array("OH", 43001, 45999), array("OK", 73001, 73198), array("OK", 73401, 74966),
                                     array("OR", 97001, 97920), array("PA", 15001, 19612), array("RI", 2801, 2940),
                                     array("SC", 29001, 29945), array("SD", 57001, 57799), array("TN", 37010, 38589),
                                     array("TX", 73301, 73344), array("TX", 75001, 79999), array("TX", 88510, 88595),
                                     array("UT", 84001, 84791), array("VT", 5001, 5495), array("VT", 5601, 5907),
                                     array("VA", 20101, 20198), array("VA", 20598, 20598), array("VA", 22003, 24658),
                                     array("WA", 98001, 99403), array("WV", 24701, 26886), array("WI", 53001, 54990),
                                     array("WY", 82001, 83128), array("WY", 83414, 83414), array("AS", 96799, 96799),
                                     array("GU", 96910, 96932), array("MP", 96950, 96952), array("PR", 601, 795),
                                     array("PR", 901, 988), array("VI", 801, 851), array("FM", 96941, 96944),
                                     array("MH", 96960, 96970), array("PW", 96939, 96940), array("AA", 34002, 34099),
                                     array("AE", 9002, 9898), array("AP", 96201, 96698));
    const REGEX_VALID_CA_CODE = '/^[ABCEGHJKLMNPRSTVXY]{1}\d{1}[A-Z]{1} *\d{1}[A-Z]{1}\d{1}$/i';
    // Each array contains 3 elements:
    // C_CAN_REGION_ABBREVIATION(0)     - Region abbreviation
    // C_CAN_DISTRICT_IDS(1)    - Array of the post district identifiers
    // C_CAN_REGION_NAME(2)     - Full region name
    const C_CAN_REGION_ABBREVIATION = 0;
    const C_CAN_DISTRICT_IDS = 1;
    const C_CAN_REGION_NAME = 2;
    private $CANADA_CODES_TABLE = array(array("NL",array("A"),"Newfoundland and Labrador"),
                                        array("NS",array("B"),"Nova Scotia"),
                                        array("PE",array("C"),"Prince Edward Island"),
                                        array("NB",array("E"),"New Brunswick"),
                                        array("QC",array("G","H","J"),"Quebec"),
                                        array("ON",array("K","L","M","N","P"),"Ontario"),
                                        array("MB",array("R"),"Manitoba"),
                                        array("SK",array("S"),"Saskatchewan"),
                                        array("AB",array("T"),"Alberta"),
                                        array("BC",array("V"),"British Columbia"),
                                        array("NT",array("X"),"Northwest Territories"),
                                        array("YT",array("Y"),"Yukon")
                                  );

    protected static $instance;
    static function __cmp_sort_zip_codes($a, $b){
        if ($a[1] == $b[1]) return 0;
                return ($a[1]>$b[1]) ? 1 : -1;
    }
    private function __construct(){
        usort($this->USA_CODES_TABLE, "RegionResolver::__cmp_sort_zip_codes");
    }
    private function __clone()    {  }
    private function __wakeup()   {  }
    /**
     * @static
     * @return RegionResolver
     */
    public static function getInstance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new RegionResolver;
        }
        return self::$instance;
    }

    /**
     * Returns true if given code is valid USA ZIP code(e.g "94105-0011" or "94105")
     * @param $zip
     * @return bool
     */
    public function isValidUSAZip($zip){
        return preg_match(self::REGEX_VALID_ZIP, $zip)>0;
    }

    /**
     * Returns true if given code is valid Canadian postal code("T2X 1V4" or "T2X1V4")
     * @param $postalCode
     * @return bool
     */
    public function isValidCanadianPostalCode($postalCode){
        return preg_match(self::REGEX_VALID_CA_CODE, $postalCode)>0;
    }

    private function find_in_array($array, $callback, $param){
        $res = array();
        foreach($array as $x){
            if (call_user_func($callback,$x,$param)) $res[] = $x;
        }
        return $res;
    }

    static function __cmp_filter_usa_zip($element, $needle){
        $intZip = intval($needle);
        return ($intZip > $element[1] && $intZip < $element[2]) || $intZip == $element[1] || $intZip == $element[2];
    }

    public function getUSAStateByZip($zipCode){
        if (!$this->isValidUSAZip($zipCode))
            throw new InvalidArgumentException("Given code $zipCode is not valid USA ZIP code");
        if (strpos($zipCode,'-'))
            $zipCode = substr($zipCode, 0,strpos($zipCode, '-'));
        $result = $this->find_in_array($this->USA_CODES_TABLE, "RegionResolver::__cmp_filter_usa_zip", $zipCode);
        if (count($result)==0) return null;
        $keys = array_keys($result);
        return $result[$keys[0]][self::C_USA_STATE_ABBREVIATION];
    }

    static function __cmp_filter_ca_postal_code($element, $needle){
        return in_array($needle,$element[1]);
    }

    public function getCanadianProvinceInfoByPostalCode($postalCode){
        if (!$this->isValidCanadianPostalCode($postalCode)){
            throw new InvalidArgumentException("Given code $postalCode is not valid Canadian postal code");
        }
        $districtId = strtoupper(substr($postalCode,0,1));
        $result = $this->find_in_array($this->CANADA_CODES_TABLE, "RegionResolver::__cmp_filter_ca_postal_code", $districtId);
        if (count($result)==0) return null;
        $keys = array_keys($result);
        return $result[$keys[0]];
    }

    public function getCanadianProvinceAbbrByPostalCode($postalCode){
        $provinceInfo = $this->getCanadianProvinceInfoByPostalCode($postalCode);
        if ($provinceInfo == null) return null;
        return $provinceInfo[self::C_CAN_REGION_ABBREVIATION];
    }

    public function getCanadianProvinceNameByPostalCode($postalCode){
        $provinceInfo = $this->getCanadianProvinceInfoByPostalCode($postalCode);
        if ($provinceInfo == null) return null;
        return $provinceInfo[self::C_CAN_REGION_NAME];
    }

    /**
     * Determines State or Province by given postal code. If cod if not valid USA or Canadian postal cod - returns null
     * @param $postalCode
     * @return USA State or name of the Canadian province
     */
    public function getStateOrProvinceByCode($postalCode){
        if ($this->isValidUSAZip($postalCode)){
            return $this->getUSAStateByZip($postalCode);
        }else if($this->isValidCanadianPostalCode($postalCode)) {
            return $this->getCanadianProvinceNameByPostalCode($postalCode);
        }else{
            return null;
        }
    }
}