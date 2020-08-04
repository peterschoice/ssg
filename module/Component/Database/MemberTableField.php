<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오전 10:36
 */

namespace Component\Database;


class MemberTableField extends \Bundle\Component\Database\MemberTableField
{
    public function __construct()
    {
        parent::__construct();
    }

    public function tableMember()
    {
        $arrField = parent::tableMember();
        $arrField[] = ['val' => 'coninfo','typ' => 's','def' => null]; // CI
        $arrField[] = ['val' => 'ssgdfmFl','typ' => 's','def' => 'n']; // Ssgdfm 연동여부
        $arrField[] = ['val' => 'staffNo','typ' => 's','def' => null]; // Ssgdfm 임직원 사번
        $arrField[] = ['val' => 'staffFl','typ' => 's','def' => 'n']; // Ssgdfm 임직원 연동여부
        return $arrField;
    }
}