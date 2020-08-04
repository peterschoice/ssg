<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오전 11:28
 */

namespace Component\Webidas;


class SsgdfmSimpleType
{
    /** @var  string */
    protected $_typeName;

    /** @var bool  */
    protected $_isArrayType = false;

    protected $value = null;

    public function __construct($typeName = 'string') {
        $this->_typeName = $typeName;
    }

    // set the value, as there is a name clash with the attribute-value class
    // we choose this name !
    public function setTypeValue( $value )
    {
        $this->value = $value;
    }

    // get the value, as there is a name clash with the attribute-value class
    // we choose this name !
    public function getTypeValue()
    {
        return $this->value;
    }

    public static function makeValue($value, $type) {

        $typeCase = strtolower($type);

        switch($typeCase) {
            case 'int':
                return (int)$value;
            default:
                return $value;
        }
    }

    public function serialize( $elementName, $value, $typeName )
    {
        if (isset($value)) {
            if ($value) {
                $ret = $value;
                return $ret;
            } else {
                return null;
            }
        } else
        {
            return null;
        }
    }
}