<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-19
 * Time: 오후 4:34
 */

namespace Component\Member;


class MemberVO extends \Bundle\Component\Member\MemberVO
{
    protected $staffNo;
    protected $ssgdfmFl;
    protected $staffFl;

    function __construct(array $arr = null)
    {
        parent::__construct($arr);
    }

    /**
     * @return mixed
     */
    public function getStaffNo()
    {
        return $this->staffNo;
    }

    /**
     * @param mixed $staffNo
     */
    public function setStaffNo($staffNo)
    {
        $this->staffNo = $staffNo;
    }

    /**
     * @return mixed
     */
    public function getSsgdfmFl()
    {
        return $this->ssgdfmFl;
    }

    /**
     * @param mixed $ssgdfmFl
     */
    public function setSsgdfmFl($ssgdfmFl)
    {
        $this->ssgdfmFl = $ssgdfmFl;
    }

    /**
     * @return mixed
     */
    public function getStaffFl()
    {
        return $this->staffFl;
    }

    /**
     * @param mixed $staffFl
     */
    public function setStaffFl($staffFl)
    {
        $this->staffFl = $staffFl;
    }




}