<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오후 1:46
 */

namespace Component\Webidas;


class BaseResponseType extends SsgdfmErrorType
{

    public function __construct()
    {
        parent::__construct('BaseResponseType');
        if (!isset(self::$_elements[__CLASS__]))
        {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class()],[]);
        }
    }

}