<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-15
 * Time: 오후 5:31
 */

namespace Component\Webidas\SsgdfmException;

use Exception;

class SsgdfmBuildException extends Exception
{
    const TRANSACTION_DUPLICATE = 1;
}