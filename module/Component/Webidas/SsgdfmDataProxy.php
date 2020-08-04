<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오후 3:56
 */

namespace Component\Webidas;

use Bundle\Component\Member\MemberAdmin;
use Bundle\Component\Member\MemberDAO;
use Exception;

class SsgdfmDataProxy
{
    /** @var \Framework\Database\DBTool|null */
    protected $db;

    protected $memberDAO;

    protected $encryptKey = 'ssgdfs2020!';
    protected $encryptIv = 'ssg_dfm';



    /**
     * @var array
     * @date 2020-07-15 14:10:20 junlae.kim@webidas.com
     * @see sexFl은 쓱special에서 미수집항목으로 정책화되어 있음
     */

    public $apiMap = [
        'ciNo'=>'coninfo',
        'userName'=>'memNm',
        'userId'=>'memId',
        'hpTellNum'=>'cellPhone',
        'birthDt'=>'birthDt',
        'sexGbn'=>'sexFl',
        'mailAddr'=>'email',
        'mailRecvYn'=>'maillingFl',
        'smsRecvYn'=>'smsFl',
        'empNo'=>'staffNo' // 사번
    ];
    public $apiFormMap =[
        'ciNo'=>'CI',
        'userId'=>'memId',
        'userName'=>'memNm',
        'mailAddr'=>'email',
        'sexGbn'=>'sexFl',
        'birthDt'=>'birthDt',
        'hpTellNum'=>'cellPhone',
        'empNo'=>'staffNo'
    ];

    public $apiMapName = [
        'ciNo'=>'본인인증정보',
        'userName'=>'회원명',
        'userId'=>'회원아이디',
        'hpTellNum'=>'핸드폰번호',
        'birthDt'=>'생년월일',
        'sexGbn'=>'성별',
        'mailAddr'=>'이메일',
        'mailRecvYn'=>'메일수신여부',
        'smsRecvYn'=>'문자수신여부',
        'empNo'=>'사번' // 사번
    ];


    const STAFF_GROUP_SNO = 2;

    public function __construct()
    {
        if(is_object($this->db)) {
            $this->db = \App::load('DB');
        }
        $this->memberDAO = new MemberDAO();

    }


    public function validate($inputVal, $serviceName, $inputFormat = 'db') {
        $definedMap = $inputFormat == 'form' ? $this->apiFormMap : $this->apiMap;
        switch($serviceName) {
            case 'GetDuplicate':
                foreach ($definedMap as $key=>$val) {
                    if(in_array($key, ['ciNo','userId'])) {

                        $validKey =  $inputFormat == 'form' ? $val : $key;

                        if(!gd_isset($inputVal[$validKey])) {
                            return [$val, $this->apiMapName[$key]];
                        }
                    } else {
                        continue;
                    }
                }
                break;
            default:
                foreach ($definedMap as $key=>$val) {
                    if(in_array($key, ['mailRecvYn','smsRecvYn']) && ($serviceName == 'GetMember' || $serviceName=='GetStaff')) {
                        continue;
                    }
                    if(($serviceName == 'GetMember' || $serviceName=='SetJoin') && $key=='empNo') {
                        continue;
                    }

                    $validKey =  $inputFormat == 'form' ? $val : $key;

                    if(!gd_isset($inputVal[$validKey])) {
                        return [$val, $this->apiMapName[$key]];
                    }
                }
                break;
        }
        return [];
    }

    public function encrypt($data) {

        $inspectData = [];
        $aesData = openssl_encrypt($data, 'AES-128-CBC', md5($this->encryptKey), OPENSSL_RAW_DATA, md5($this->encryptIv));
        $inspectData['aes']=$aesData;
        $base64EncodeData = base64_encode($aesData);
        $inspectData['base64'] = $base64EncodeData;
        $encryptData = urlencode($base64EncodeData);
        $inspectData['urlencode'] = $encryptData;
        Webidas::dumper($inspectData);
        return $encryptData;
    }

    public function getSendFormData($postVal) {
        $getData = [];
        foreach($this->apiFormMap as $k=>$v) {
            if(gd_isset($postVal[$v])) {
                $getData[$k] = $postVal[$v];
            }
        }
        return $getData;
    }

    public function getSendFl($memNo) {
        $memberData = $this->memberDAO->selectMemberByOne($memNo);
        return ['ssgdfmFl'=>$memberData['ssgdfmFl'], 'staffFl'=>$memberData['staffFl']];
    }

    public function getSendData($memNo) {

        $memberData = $this->memberDAO->selectMemberByOne($memNo);

        //Webidas::dumper($memberData);

        $getData = [];
        foreach($this->apiMap as $k=>$v) {
            $getData[$k] = $memberData[$v];
        }
        return $getData;
    }

    public function setMemberSync($memNo, $syncName = 'ssgdfmFl', $syncVal = 'r') {
        $this->memberDAO->updateMember([$syncName=>$syncVal, 'memNo'=>$memNo],[$syncName], []);
    }

    public function applyStaffGrade($memNo) {
        $memberAdmin = new MemberAdmin();
        try {
            $result = $memberAdmin->applyGroupGradeByMemberNo(self::STAFF_GROUP_SNO, [$memNo]);
            $beforeMembers = $memberAdmin->getBeforeMembersByGroupBatch();
            $members = $memberAdmin->getAfterMembersByGroupBatch();
            $memberAdmin->writeGroupChangeHistory($beforeMembers, $members);
            return true;
        } catch (Exception $e) {
            // 회원번호가 없거나 변경회원등급이 없는 경우
            return false;
        }
    }

}