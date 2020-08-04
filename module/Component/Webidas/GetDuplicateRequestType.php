<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-15
 * Time: 오전 9:46
 */

namespace Component\Webidas;


class GetDuplicateRequestType extends BaseRequestType
{
    /** @var string */
    protected $ciNo;
    /**
     * @var string UPPER CASE
     */
    protected $userId;

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

    public function __construct()
    {
        parent::__construct('GetDuplicateRequestType');
        if (!isset(self::$_elements[__CLASS__]))
        {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class()],[
                'ciNo'=>[
                    'required'=>true,
                    'type'=>'string',
                    'array'=>false
                ],
                'userId'=>[
                    'required'=>false,
                    'type'=>'string',
                    'array'=>false
                ],
             ]);
        }
    }
}