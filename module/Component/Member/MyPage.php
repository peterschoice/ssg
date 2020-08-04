<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-19
 * Time: 오후 5:10
 */

namespace Component\Member;

use App;
use Component\Webidas\Webidas;
use Exception;
use Logger;
use Bundle\Component\Member\Util\MemberUtil;
use Bundle\Component\Member\MemberDAO;
use Bundle\Component\Member\Member;
use Bundle\Component\Mail\MailMimeAuto;
use Bundle\Component\Validator\Validator;
use Framework\Utility\StringUtils;
use Session;

class MyPage extends \Bundle\Component\Member\MyPage
{

    /** @var \Bundle\Component\Mail\MailMimeAuto $mailMimeAuto */
    private $mailMimeAuto;
    /** @var  \Bundle\Component\Member\MemberDAO */
    private $memberDao;
    /** @var \Component\Member\Member $member */
    private $member;

    public function __construct(MailMimeAuto $mailMimeAuto = null, Member $member = null)
    {
        parent::__construct($mailMimeAuto, $member);
        if ($mailMimeAuto === null) {
            $this->mailMimeAuto = App::load('\\Component\\Mail\\MailMimeAuto');
        }
        if ($member === null) {
            $this->member = App::load('\\Component\\Member\\Member');
        }
        $this->memberDao = new MemberDAO();
    }

    /**
     * 프론트 마이페이지 회원정보 수정
     *
     * @param array $requestParams
     * @param array $memberSession
     *
     * @throws Exception
     */
    public function modify(array $requestParams, array $memberSession)
    {
        $logger = \App::getInstance('logger');
        $logger->info(__METHOD__, $requestParams);
        $logger->debug(__METHOD__, $memberSession);
        // 토글형 체크박스는 체크하지 않을 경우 값이 없으므로 n으로 설정함
        gd_isset($requestParams['maillingFl'], 'n');
        gd_isset($requestParams['smsFl'], 'n');

        if ($memberSession['mallSno'] == DEFAULT_MALL_NUMBER) {
            \Component\Member\MemberValidation::checkJoinPhoneCode($requestParams);
        }

        //이용약관 체크값 설정
        $agreementData = $this->setAgreementData($requestParams);

        $requestParams = MemberUtil::combineMemberData($requestParams);


        //Webidas::dumper($requestParams);

        $requestParams['privateApprovalOptionFl'] = json_encode($agreementData['privateApprovalOptionFl'], JSON_UNESCAPED_SLASHES);
        $requestParams['privateConsignFl'] = json_encode($agreementData['privateConsignFl'], JSON_UNESCAPED_SLASHES);
        $requestParams['privateOfferFl'] = json_encode($agreementData['privateOfferFl'], JSON_UNESCAPED_SLASHES);

        $passValidation = isset($memberSession['memPw']) == false && $memberSession['snsJoinFl'] == 'y';
        $requestParams = $this->validateMemberByModification($requestParams, $passValidation);

        //Webidas::dumper($requestParams);

        $addExclude = [
            'memPw',
            'groupSno',
            'groupModDt',
            'groupValidDt',
        ];

        // 평생회원 이벤트
        if ($requestParams['expirationFl'] === '999') {
            $requestParams['lifeMemberConversionDt'] = date('Y-m-d H:i:s');
        }

        // 비밀번호 수정일 경우 검증
        if (gd_isset($requestParams['memPw'], '') != '') {
            $logger->info('memPw is not empty. verify current password');
            if (isset($memberSession['memNo']) === false) {
                $logger->info('modify session is empty');
                throw new Exception(__('로그인 정보가 없습니다.'));
            }

            if (Validator::number($memberSession['memNo'], null, null, true) === false) {
                $logger->info('invalid member number');
                throw new Exception(__('유효하지 않은 회원번호 입니다.'));
            }

            $memberPassword = $this->memberDao->selectPassword($memberSession['memNo']);

            if ($passValidation == false) {
                // @todo : 로그인 시점에 hash 함수로 변경하므로 legacy 체크 필요 없으나, 그래도 혹시 모르니 확인해 볼것.
                $logger->debug(
                    'check old member password with new member password', [
                        $requestParams['oldMemPw'],
                        $memberPassword['memPw'],
                    ]
                );
                $verifyPassword = App::getInstance('password')->verify($requestParams['oldMemPw'], $memberPassword['memPw']);
                if ($verifyPassword === false) {
                    $logger->info('not equal old password');
                    throw new Exception(__('입력하신 현재 비밀번호가 틀렸습니다.'));
                }

                if ($requestParams['memPw'] === $requestParams['oldMemPw']) {
                    $logger->info('equal old password');
                    throw new Exception(__('현재 비밀번호와 동일한 비밀번호입니다.'));
                }
            }

            if ($requestParams['memPw'] !== gd_isset($requestParams['memPwRe'], '')) {
                $logger->info('not equal new password and new password repeat');
                throw new Exception(__('비밀번호가 다릅니다. 다시 확인 바랍니다.'));
            }

            $requestParams['memPw'] = App::getInstance('password')->hash($requestParams['memPw']);
            $passwordDt = new \DateTime();
            $requestParams['changePasswordDt'] = $passwordDt->format('Y-m-d H:i:s');
            unset($addExclude[array_search('memPw', $addExclude)]);
            Session::set(self::SESSION_MY_PAGE_PASSWORD, true);
            $logger->info('password verify complete');
        } else {
            $logger->info('member password is not change');
        }

        $this->memberDao->updateMember($requestParams, [], $addExclude);
        $memberWithGroup = $this->memberDao->selectMemberWithGroup($requestParams['memNo'], 'memNo');
        $this->_refreshSession($memberWithGroup);

        // 추천인 등록시 혜택 지급
        if (empty($memberSession['recommId']) && $memberSession['recommFl'] != 'y' && empty($requestParams['recommId']) == false) {
            $benefit = \App::load('Component\\Member\\Benefit');
            $benefit->benefitMoidfyRecommender($requestParams);
            unset($benefit);
        }
    }

    /**
     * validateMemberByModification wrapping 함수
     *
     * @param      $requestParams
     * @param bool $passValidation
     *
     * @return mixed
     */
    protected function validateMemberByModification($requestParams, $passValidation = false)
    {
        $validateRequestParams = $this->_validateMemberByModification($requestParams, $passValidation);
        if ($requestParams['staffNo'] != '') {
            $validateRequestParams['staffNo'] = $requestParams['staffNo'];
        }
        return $validateRequestParams;
    }

    /**
     * 마이페이지 내정보 수정 입력 값 검증
     *
     * @param      $requestParams
     * @param bool $passValidation
     *
     * @return mixed
     * @throws Exception
     */
    private function _validateMemberByModification($requestParams, $passValidation = false)
    {
        if (Validator::required($requestParams['memNo']) === false) {
            throw new Exception(__('회원정보가 없습니다.'));
        }
        $require = MemberUtil::getRequireField();
        $length = MemberUtil::getMinMax();
        $joinItemPolicy = \Component\Policy\JoinItemPolicy::getInstance()->getPolicy();
        StringUtils::strIsSet($joinItemPolicy['passwordCombineFl'], '');
        StringUtils::strIsSet($joinItemPolicy['busiNo']['charlen'], 10); // 사업자번호 길이

        if (isset($requestParams['birthYear']) === true && isset($requestParams['birthMonth']) === true && isset($requestParams['birthDay']) === true) {
            $requestParams['birthDt'] = $requestParams['birthYear'].'-'.$requestParams['birthMonth'].'-'.$requestParams['birthDay'];
        }
        // 데이터 조합
        MemberUtil::combineMemberData($requestParams);
        $v = new Validator();
        $v->init();
        $v->add('memId', 'userid', true, '{' . __('아이디') . '}', true, false);
        $v->add('memNo', 'number', true);
        $v->add('privateApprovalOptionFl', '', false, '{' . __('개인정보 수집 및 이용') . '}');
        $v->add('privateOfferFl', '', false, '{' . __('개인정보동의 제3자 제공') . '}');
        $v->add('privateConsignFl', '', false, '{' . __('개인정보동의 취급업무 위탁') . '}');
        if (StringUtils::strIsSet($requestParams['memPw'], '') !== '') {
            \Component\Member\MemberValidation::validateMemberPassword($requestParams['memPw']);
            $v->add('memPw', '', true, '{' . __('비밀번호') . '}');
            $v->add('memPwRe', '', true, '{' . __('비밀번호 확인') . '}');
            if (StringUtils::strIsSet($requestParams['oldMemPw'], '') != '') {
                $v->add('oldMemPw', '', true, '{' . __('현재 비밀번호') . '}');
            }
        }
        if (isset($requestParams['marriFl']) === true && $requestParams['marriFl'] == 'y') {
            if (isset($requestParams['marriYear']) === true && isset($requestParams['marriMonth']) === true && isset($requestParams['marriDay']) === true) {
                $requestParams['marriDate'] = $requestParams['marriYear'].'-'.$requestParams['marriMonth'].'-'.$requestParams['marriDay'];
            }
            $v->add('marriDate', '', $require['marriDate'], '{' . __('결혼기념일') . '}'); // 결혼기념일
        } elseif (isset($requestParams['marriFl']) === true && $requestParams['marriFl'] == 'n') {
            $v->add('marriDate', '', false, '{' . __('결혼기념일') . '}'); // 결혼기념일
            $requestParams['marriDate'] = '';
        }
        \Component\Member\MemberValidation::addValidateMember($v);
        \Component\Member\MemberValidation::addValidateMemberExtra($v, $require);
        if (isset($requestParams['memberFl']) === true && $requestParams['memberFl'] == 'business') {
            \Component\Member\MemberValidation::addValidateMemberBusiness($v, $require);
        }
        if ($joinItemPolicy['pronounceName']['use'] == 'y') {
            $v->add('pronounceName', '', $joinItemPolicy['pronounceName']['require'], '{' . __('이름(발음)') . '}');
        }
        if ($requestParams['dupeinfo'] != '') {
            $v->add('dupeinfo', '');
        }
        if ($requestParams['rncheck'] != '') {
            $v->add('rncheck', '');
        }

        if ($v->act($requestParams, true) === false) {
            if (key_exists('memPw', $v->errors) && key_exists('memPwRe', $v->errors)) {
                unset($v->errors['memPwRe']);
            }
            throw new Exception(implode("\n", $v->errors), 500);
        }

        // 닉네임 중복여부 체크
        if ($require['nickNm'] || !empty($requestParams['nickNm'])) {
            if (MemberUtil::overlapNickNm($requestParams['memId'], $requestParams['nickNm'])) {
                Logger::error(__METHOD__ . ' / ' . sprintf('%s는 이미 사용중인 닉네임입니다', $requestParams['nickNm']));
                throw new Exception(sprintf(__('%s는 이미 사용중인 닉네임입니다'), $requestParams['nickNm']));
            }
        }

        // 이메일 중복여부 체크
        if ($require['email'] || !empty($requestParams['email'])) {
            if (MemberUtil::overlapEmail($requestParams['memId'], $requestParams['email'])) {
                Logger::error(__METHOD__ . ' / ' . sprintf('%s는 이미 사용중인 이메일입니다', $requestParams['email']));
                throw new Exception(sprintf(__('%s는 이미 사용중인 이메일입니다'), $requestParams['email']));
            }
        }

        // 추천아이디 실존인물인지 체크
        if ($require['recommId'] || !empty($requestParams['recommId'])) {
            if (MemberUtil::checkRecommendId($requestParams['recommId'], $requestParams['memId']) === false) {
                throw new Exception(sprintf(__('등록된 회원 아이디가 아닙니다. 추천하실 아이디를 다시 확인해주세요.'), $requestParams['recommId']));
            }
        }

        // 사업자번호 중복여부 체크
        if ($requestParams['memberFl'] == 'business' && ($require['busiNo'] || !empty($requestParams['busiNo']))) {
            $newBusiNo = gd_remove_special_char($requestParams['busiNo']);
            $memberData = \Component\Member\MyPage::myInformation();
            $oldBusiNo = $memberData['busiNo'];

            if (strlen($newBusiNo) != $joinItemPolicy['busiNo']['charlen']) {
                throw new Exception(sprintf(__('사업자번호는 %s자로 입력해야 합니다.'), $joinItemPolicy['busiNo']['charlen']));
            }

            if ($newBusiNo != $oldBusiNo && $joinItemPolicy['busiNo']['overlapBusiNoFl'] == 'y' && MemberUtil::overlapBusiNo($requestParams['memId'], $newBusiNo)) {
                throw new Exception(sprintf(__('%s - 이미 등록된 사업자번호입니다.'), $requestParams['busiNo']));
            }
        }
        return $requestParams;
    }

    /**
     * 로그인 세션 데이터 갱신
     *
     * @param object $memberData 회원정보
     */
    private function _refreshSession($memberData)
    {
        $memInfo = MemberUtil::encryptMember($memberData);
        Session::set(Member::SESSION_MEMBER_LOGIN, $memInfo);
    }

}