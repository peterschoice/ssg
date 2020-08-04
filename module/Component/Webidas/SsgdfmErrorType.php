<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오전 11:28
 */

namespace Component\Webidas;


class SsgdfmErrorType extends SsgdfmComplexType
{

    public static $resultCodeType = [
        200=>'비회원(회원가입가능상태)',
        //1000=>'비회원 (회원가입 가능상태)',
        1001=>'비회원 이지만, 다른 사용자가 ID 사용중',
        1002=>'이미 사용중인 아이디입니다.',
        1005=>'해당 CI로 사용중인 아이디 존재.',
        2000=>'이미 가입된 회원',
        2001=>'휴면회원',
        2002=>'탈퇴회원(같은아이디 가입불가)',
        3000=>'임직원 입니다',
        4000=>'임직원이 아닙니다',
        9000=>'파라미터오류 (회원아이디)', // NULL / LENGTH
        9001=>'파라미터오류 (회원 CI)',
        9002=>'파라미터오류 (이름)',
        9003=>'파라미터오류 (사원번호)',
        9004=>'파라미터오류 (생년월일)',
        9005=>'파라미터오류 (회원성별)',
        9006=>'파라미터오류 (API유형)',
        9010=>'중복 transactionID',
        9011=>'이미 임직원입니다', // 신세계몰 임직원 연동시
    ];

    /** @var int */
    protected $resultCode;

    /** @var string */
    protected $resultMsg;

    /** @var string */
    protected $resultValue;

    /**
     * @return int
     */
    public function getResultCode()
    {
        return $this->resultCode;
    }

    /**
     * @param string $resultCode
     */
    public function setResultCode($resultCode)
    {
        $this->resultCode = $resultCode;
    }

    /**
     * @return string
     */
    public function getResultMsg()
    {
        return $this->resultMsg;
    }

    /**
     * @param string $resultMsg
     */
    public function setResultMsg($resultMsg)
    {
        $this->resultMsg = $resultMsg;
    }

    /**
     * @return string
     */
    public function getResultValue()
    {
        return $this->resultValue;
    }

    /**
     * @param string $resultValue
     */
    public function setResultValue($resultValue)
    {
        $this->resultValue = $resultValue;
    }

    /**
     * Class Constructor
     **/
    public function __construct()
    {
        parent::__construct('SsgdfmErrorType');
        if (!isset(self::$_elements[__CLASS__])) {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class()],
                [

                    'resultCode' => [
                        'required' => true,
                        'type' => 'int',
                        'array' => false,
                        'cardinality' => '0..1'
                    ],
                    'resultMsg' => [
                        'required' => false,
                        'type' => 'string',
                        'array' => false,
                        'cardinality' => '0..1'
                    ],
                    'resultValue' => [
                        'required' => false,
                        'type' => 'string',
                        'array' => false,
                        'cardinality' => '0..1'
                    ]
                ]);
        }
    }
}