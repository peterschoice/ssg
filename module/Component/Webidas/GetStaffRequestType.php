<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-15
 * Time: 오전 9:51
 */

namespace Component\Webidas;


class GetStaffRequestType extends BaseRequestType
{
    /** @var string */
    protected $ciNo;

    protected $userName;
    /**
     * @var string UPPER CASE
     */
    protected $userId;

    protected $hpTellNum;

    protected $birthDt;

    protected $sexGbn;

    protected $mailAddr;

    /** @var string */
    protected $empNo;

    /**
     * @return string
     */
    public function getCiNo()
    {
        return $this->ciNo;
    }

    /**
     * @param string $ciNo
     */
    public function setCiNo($ciNo)
    {
        $this->ciNo = $ciNo;
    }

    /**
     * @return mixed
     */
    public function getUserName()
    {
        return $this->userName;
    }

    /**
     * @param mixed $userName
     */
    public function setUserName($userName)
    {
        $this->userName = $userName;
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param mixed $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return mixed
     */
    public function getHpTellNum()
    {
        return $this->hpTellNum;
    }

    /**
     * @param mixed $hpTellNum
     */
    public function setHpTellNum($hpTellNum)
    {
        $this->hpTellNum = $hpTellNum;
    }

    /**
     * @return mixed
     */
    public function getBirthDt()
    {
        return $this->birthDt;
    }

    /**
     * @param mixed $birthDt
     */
    public function setBirthDt($birthDt)
    {
        $this->birthDt = $birthDt;
    }

    /**
     * @return mixed
     */
    public function getSexGbn()
    {
        return $this->sexGbn;
    }

    /**
     * @param mixed $sexGbn
     */
    public function setSexGbn($sexGbn)
    {
        $this->sexGbn = $sexGbn;
    }

    /**
     * @return mixed
     */
    public function getMailAddr()
    {
        return $this->mailAddr;
    }

    /**
     * @param mixed $mailAddr
     */
    public function setMailAddr($mailAddr)
    {
        $this->mailAddr = $mailAddr;
    }

    /**
     * @return string
     */
    public function getEmpNo()
    {
        return $this->empNo;
    }

    /**
     * @param string $empNo
     */
    public function setEmpNo($empNo)
    {
        $this->empNo = $empNo;
    }

    public function __construct()
    {
        parent::__construct('GetStaffRequestType');
        if (!isset(self::$_elements[__CLASS__]))
        {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class()],[
                'ciNo'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'userName'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'userId'=>[
                    'required'=>false,
                    'type'=>'string',
                    'array'=>false
                ],
                'hpTellNum'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'birthDt'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'sexGbn'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'mailAddr'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'empNo'=>[
                    'required'=>false,
                    'type'=>'string',
                    'array'=>false
                ]
            ]);
        }
    }
}