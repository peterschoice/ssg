<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오전 11:55
 */

namespace Component\Webidas;


class BaseRequestType extends SsgdfmComplexType
{

    protected $transaction_id;

    /**
     * @var string
     * @see D: 회원여부 , E:임직원 여부 , J : 회원가입시, I: 아이디중복체크
     */
    protected $apiCode;

    /**
     * @return string
     */
    public function getApiCode()
    {
        return $this->apiCode;
    }

    /**
     * @param string $apiCode
     */
    public function setApiCode($apiCode)
    {
        $this->apiCode = $apiCode;
    }
    /**
     * @return mixed
     */
    public function getTransactionId()
    {
        return $this->transaction_id;
    }

    /**
     * @param mixed $transaction_id
     */
    public function setTransactionId($transaction_id)
    {
        $this->transaction_id = $transaction_id;
    }

    public function __construct()
    {
        parent::__construct('BaseRequestType');
        if (!isset(self::$_elements[__CLASS__]))
        {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class()],[
                'apiCode'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'transaction_id'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ]
             ]);
        }
    }
}