<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-09
 * Time: 오후 5:57
 */

namespace Component\Member;

use Component\Webidas\Webidas;
use Framework\Object\SimpleStorage;
use Bundle\Component\Member\MemberDAO;
use Bundle\Component\Sms\SmsAuto;
use Exception;
use Request;

/**
 * Class Member
 * @package Component\Member
 * @property \Framework\Database\DBTool $db
 */
class Member extends \Bundle\Component\Member\Member
{

    /**
     * @var array
     * @date 2020-07-16 15:48:32 junlae.kim@webidas.com
     * @see 신세계면세점 회원가입(개인정보 수집및이용) 동의 22
     * @see 신세계면세점 회원가입을 위한 제3자정보제공동의
     */
    public static $privateApprovalSnos = [
        'option'=>22,
        'staffOption'=>33,
        'offer'=>5
    ];

    /** @var mixed 실명인증 정보를 담고 있는 스토리지 컨테이너 */
    /** @var SimpleStorage */
    public $dreamSessionStorage;

    /** @var \Bundle\Component\Mail\MailMimeAuto $mailMimeAuto */
    private $mailMimeAuto;
    /** @var  \Bundle\Component\Member\MemberDAO */
    private $memberDAO;
    /** @var  \Bundle\Component\Sms\SmsAuto */
    private $smsAuto;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (isset($config['memberDao']) && \is_object($config['memberDao'])) {
            $this->memberDAO = $config['memberDao'];
        } else {
            $this->memberDAO = \App::load(MemberDAO::class);
        }

        if (isset($config['smsAuto']) && \is_object($config['smsAuto'])) {
            $this->smsAuto = $config['smsAuto'];
        } else {
            $this->smsAuto = \App::load(SmsAuto::class);
        }
    }

    public static function getPrivateApprovalSnos() {
        return self::$privateApprovalSnos;
    }

    /**
     * {@inheritdoc}
     */
    public function join($params) {

        /**
         * @date 2020-07-10 09:03:03 junlae.kim@webidas.com
         * @see 회원저장후에 세션을 삭제하므로 별도로 변수에 담아놓음
         */
        $session = \App::getInstance('session');
        $this->dreamSessionStorage = new SimpleStorage($session->get(Member::SESSION_DREAM_SECURITY));
        //Webidas::dumper($this->dreamSessionStorage);

        try {
            $vo = parent::join($params);
            return $vo;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @return SimpleStorage
     */
    public function getDreamSessionStorage() {
        return $this->dreamSessionStorage;
    }

    public function setMemberDreamSessionStorage($memNo, SimpleStorage $dreamSessionStorage) {
        $this->update($memNo, 'memNo', ['coninfo'], [$dreamSessionStorage->get('CI')]);
    }

    public function setMemberSsgdfm($memNo) {
        $this->update($memNo, 'memNo', ['ssgdfmFl'], ['y']);
    }

    /**
     * 탈퇴회원 검증
     *
     * @param array $hackOutParams
     *
     * @throws Exception
     */
    private function _validateHackOuMember(array $hackOutParams)
    {
        $tmpData = $this->getDataByTable(DB_MEMBER_HACKOUT, array_values($hackOutParams), array_keys($hackOutParams));
        if (empty($tmpData['memNo']) === false) {
            \Logger::channel('userLogin')->warning('회원 탈퇴 or 탈퇴신청 회원.', [$this->getRequestData()]);
            throw new Exception(__('회원 탈퇴를 신청하였거나, 탈퇴한 회원이십니다.<br/>로그인이 제한됩니다.'), 500);
        }
        unset($tmpData, $tmpArrBind);
    }

    /**
     * 승인체크
     *
     * @param $applyFlag
     *
     * @throws Exception
     */
    private function _validateApplyFlag($applyFlag)
    {
        if ($applyFlag != 'y') {
            \Logger::channel('userLogin')->warning('본 사이트 미승인으로 인한 로그인 제한', [$this->getRequestData()]);
            throw new Exception(__('고객님은 본 사이트에서 승인되지 않아 로그인이 제한됩니다.'), 500);
        }
    }

    /**
     * 성인정보관련 , 1년이 지난경우는 재인증필요
     *
     * @param array $member
     */
    private function _checkAdultFlagAndUpdate(array &$member)
    {
        if ($member['adultFl'] == 'y' && (strtotime($member['adultConfirmDt']) < strtotime("-1 year", time()))) {
            $member['adultFl'] = "n";
        }
    }

    /**
     * 기술지원지 필요한 정보들
     *
     * @param mixed $data
     *
     * @return string
     */
    private function getRequestData($data = [])
    {
        $data['PAGE_URL'] = Request::getDomainUrl() . Request::getRequestUri();
        $data['POST'] = Request::post()->toArray();
        unset($data['POST']['loginPwd']);
        $data['GET'] = Request::get()->toArray();
        $data['USER_AGENT'] = Request::getUserAgent();
        $data['SESSION'] = \Session::get('member');
        unset($data['SESSION']['memPw'], $data['SESSION']['memNm'], $data['SESSION']['nickNm'], $data['SESSION']['cellPhone'], $data['SESSION']['email']);
        $data['COOKIE'] = \Cookie::all();
        $data['REFERER'] = Request::getReferer();
        $data['REMOTE_ADDR'] = Request::getRemoteAddress();
        if (empty($data) === false) {
            $data['DATA'] = $data;
        }

        return $data;
    }
}