<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오전 11:29
 */

namespace Component\Webidas;


class SsgdfmComplexType extends SsgdfmSimpleType
{
    protected static $_elements = array();

    // will define wheather the data is stored in the value-field (as a assoc array)
    // or either in Members of an object
    protected $_dataInValueArray = false;

    public function __construct($name) {
        self::$_elements[__CLASS__] = array();
        parent::__construct($name);
    }

    public function serialize($elementName, $value, $typeName) {

        $ret = null;
        // lets decide where we are getting the data from
        if ( $this->_dataInValueArray ) {
            $ret = null;
            foreach ( $value as $key => $data ) {
                if ( $data instanceof SsgdfmSimpleType ) {
                    /** @var JsonClientSimpleType $data */
                    $ret[$key]= $data->serialize($key, $data, null);
                } else {
                    $ret[$key]= SsgdfmSimpleType::serialize($key, $data, null);
                }
            }
        } else {
            if ( count( $this->getMetaDataElements() ) == 0 ) {
                $ret = $this->value;
            } else {
                foreach ( $this->getMetaDataElements() as $childElementName => $childTypeInfo ) {
                    $childValue = $this->{$childElementName};
                    $childType = isset( $childTypeInfo['type']) ? $childTypeInfo['type'] : null;

                    if (is_array( $childValue)) {
                        $needArraySurrounding = null;
                        foreach ($childValue as $arrayElementValue ) {
                            if ($childValue instanceof  SsgdfmSimpleType) {
                                $ret[$childElementName]= $childValue->serialize( $childElementName, $arrayElementValue, $childType);
                            } else {
                                if (is_object($arrayElementValue)) {
                                    // hack to guess the original element name out of
                                    // the class-name of the array-element
                                    if (!$childTypeInfo['array']) {
                                        list($questedName) = explode('Type', get_class($arrayElementValue));
                                        $needArraySurrounding = $childElementName;
                                        $ret[$questedName]= $arrayElementValue->serialize( $questedName, $arrayElementValue, $childType ) ;
                                    } else {
                                        $ret[$childElementName][]=$arrayElementValue->serialize( $childElementName, $arrayElementValue, $childType);
                                    }
                                }
                                else {
                                    $ret[$childElementName] = SsgdfmSimpleType::serialize( $childElementName, $arrayElementValue, $childType);
                                }
                            }
                        }

                        if ($needArraySurrounding !== null)
                        {
                            $ret[$needArraySurrounding] = $ret;
                        }
                    } else{
                        if ( $childValue instanceof SsgdfmSimpleType ) {
                            $ret[$childElementName]= $childValue->serialize( $childElementName, $childValue,$childType );
                        } else {
                            $ret[$childElementName] = SsgdfmSimpleType::serialize( $childElementName, $childValue, $childType);
                        }
                    } // plain
                }
            }

        }
        //$ret[$elementName] = $ret;
        return $ret;
    }

    public function getMetaDataElements($class = null)
    {
        if ($class === null) {
            $class = get_class($this);
        }
        return self::$_elements[$class];
    }

}