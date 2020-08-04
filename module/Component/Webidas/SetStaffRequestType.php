<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-15
 * Time: 오전 9:39
 */

namespace Component\Webidas;


class SetStaffRequestType extends BaseRequestType
{
    /** @var string */
    protected $ciNo;

    protected $userId;

    protected $userName;

    protected $hpTellNum;

    protected $birthDt;

    protected $sexGbn;

    protected $mailAddr;

    protected $mailRecvYn;

    protected $smsRecvYn;

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
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param string $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
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
     * @return mixed
     */
    public function getMailRecvYn()
    {
        return $this->mailRecvYn;
    }

    /**
     * @param mixed $mailRecvYn
     */
    public function setMailRecvYn($mailRecvYn)
    {
        $this->mailRecvYn = $mailRecvYn;
    }

    /**
     * @return mixed
     */
    public function getSmsRecvYn()
    {
        return $this->smsRecvYn;
    }

    /**
     * @param mixed $smsRecvYn
     */
    public function setSmsRecvYn($smsRecvYn)
    {
        $this->smsRecvYn = $smsRecvYn;
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
        parent::__construct('SetStaffRequestType');
        if (!isset(self::$_elements[__CLASS__]))
        {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class()],[
                'ciNo'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'userId'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'userName'=>[
                    'required'=>true,
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
                'mailRecvYn'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'smsRecvYn'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'empNo'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ]

            ]);
        }
    }
}