<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오전 9:05
 */

namespace Controller\Front\Member;


use Component\Webidas\SsgdfmClient;
use Component\Webidas\SsgdfmException\SsgdfmIndependentException;
use Component\Webidas\Webidas;
use Component\Member\Member;
use Framework\Debug\Exception\AlertBackException;
use Framework\Debug\Exception\AlertRedirectException;
use Bundle\Component\Godo\GodoWonderServerApi;
use Bundle\Component\Godo\GodoPaycoServerApi;
use Bundle\Component\Facebook\Facebook;
use Bundle\Component\Godo\GodoNaverServerApi;
use Bundle\Component\Member\MemberSnsService;
use Bundle\Component\Godo\GodoKakaoServerApi;
use Exception;
use Session;
use Framework\PlusShop\PlusShopWrapper;
use Framework\Utility\ComponentUtils;
use Framework\Utility\StringUtils;
use Bundle\Component\Coupon\Coupon;
use Bundle\Component\Policy\SnsLoginPolicy;
use Bundle\Component\SiteLink\SiteLink;
use Bundle\Component\Member\Util\MemberUtil;
use Framework\Object\SimpleStorage;

class MemberPsController extends \Bundle\Controller\Front\Member\MemberPsController
{

    public $privateApprovalSnos = [];

    public function index()
    {
        $session = \App::getInstance('session');
        $request = \App::getInstance('request');
        $logger = \App::getInstance('logger');
        try {

            if (($request->getReferer() == $request->getDomainUrl()) || empty($request->getReferer()) === true) {
                $logger->error(__METHOD__ . ' Access without referer');
                throw new Exception(__("요청을 찾을 수 없습니다."));
            }
            /** @var  \Component\Member\Member $member */
            $member = \App::load('\\Component\\Member\\Member');

            $returnUrl = urldecode($request->post()->get('returnUrl'));
            if (empty($returnUrl) || strpos($returnUrl, "member_ps") !== false) {
                $returnUrl = $request->getReferer();
            }
            $mode = $request->post()->get('mode', $request->get()->get('mode'));
            switch($mode) {
                case 'join':
                    $memberVO = null;
                    try {
                        if ($session->has(GodoWonderServerApi::SESSION_USER_PROFILE)) {
                            \Bundle\Component\Member\Util\MemberUtil::saveJoinInfoBySession($request->post()->all());
                        }

                        $joinSessionForAfterAction = new SimpleStorage(Session::get(Member::SESSION_JOIN_INFO));

                        $memberSnsService = \App::load('Component\\Member\\MemberSnsService');
                        \DB::begin_tran();
                        $session->set('isFront', 'y');
                        if ($session->has('pushJoin')) {
                            $request->post()->set('simpleJoinFl', 'push');
                        }
                        $memberVO = $member->join($request->post()->xss()->all());
                        $session->del('isFront');
                        if ($session->has(GodoPaycoServerApi::SESSION_USER_PROFILE)) {
                            $paycoToken = $session->get(GodoPaycoServerApi::SESSION_ACCESS_TOKEN);
                            $userProfile = $session->get(GodoPaycoServerApi::SESSION_USER_PROFILE);
                            $session->del(GodoPaycoServerApi::SESSION_ACCESS_TOKEN);
                            $session->del(GodoPaycoServerApi::SESSION_USER_PROFILE);
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $paycoToken['idNo'], $paycoToken['access_token'], 'payco');
                            $paycoApi = new GodoPaycoServerApi();
                            $paycoApi->logByJoin();
                        } elseif ($session->has(Facebook::SESSION_USER_PROFILE)) {
                            $userProfile = $session->get(Facebook::SESSION_USER_PROFILE);
                            $accessToken = $session->get(Facebook::SESSION_ACCESS_TOKEN);
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $userProfile['id'], $accessToken, SnsLoginPolicy::FACEBOOK);
                        } elseif ($session->has(GodoNaverServerApi::SESSION_USER_PROFILE)) {
                            $naverToken = $session->get(GodoNaverServerApi::SESSION_ACCESS_TOKEN);
                            $userProfile = $session->get(GodoNaverServerApi::SESSION_USER_PROFILE);
                            $session->del(GodoNaverServerApi::SESSION_ACCESS_TOKEN);
                            $session->del(GodoNaverServerApi::SESSION_USER_PROFILE);
                            $memberSnsService = new MemberSnsService();
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $userProfile['id'], $naverToken['access_token'], 'naver');
                            $naverApi = new GodoNaverServerApi();
                            $naverApi->logByJoin();
                        } elseif ($session->has(GodoKakaoServerApi::SESSION_USER_PROFILE)) {
                            $kakaoToken = $session->get(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                            $kakaoProfile = $session->get(GodoKakaoServerApi::SESSION_USER_PROFILE);
                            $session->del(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                            $session->del(GodoKakaoServerApi::SESSION_USER_PROFILE);
                            $memberSnsService = new MemberSnsService();
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $kakaoProfile['id'], $kakaoToken['access_token'], 'kakao');
                        } elseif ($session->has(GodoWonderServerApi::SESSION_USER_PROFILE)) {
                            $wonderToken = $session->get(GodoWonderServerApi::SESSION_ACCESS_TOKEN);
                            $userProfile = $session->get(GodoWonderServerApi::SESSION_USER_PROFILE);
                            $session->del(GodoWonderServerApi::SESSION_ACCESS_TOKEN);
                            $session->del(GodoWonderServerApi::SESSION_USER_PROFILE);
                            $memberSnsService = new MemberSnsService();
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $userProfile['mid'], $wonderToken['access_token'], 'wonder');
                            $wonderApi = new GodoWonderServerApi();
                            $wonderApi->logByJoin();
                        }
                        \DB::commit();
                    } catch (\Throwable $e) {
                        \DB::rollback();
                        if (get_class($e) == Exception::class) {
                            if ($e->getMessage()) {
                                $this->js("alert('" . $e->getMessage() . "');window.parent.callback_not_disabled();");
                            }
                        } else {
                            throw $e;
                        }
                    }
                    if ($session->get('ps_event')) {
                        PlusShopWrapper::event($session->get('ps_event'), ['memNo' => $memberVO->getMemNo()]);
                    }
                    if ($memberVO != null) {
                        $smsAutoConfig = ComponentUtils::getPolicy('sms.smsAuto');
                        $kakaoAutoConfig = ComponentUtils::getPolicy('kakaoAlrim.kakaoAuto');
                        $kakaoLunaAutoConfig = ComponentUtils::getPolicy('kakaoAlrimLuna.kakaoAuto');
                        if (gd_is_plus_shop(PLUSSHOP_CODE_KAKAOALRIMLUNA) === true && $kakaoLunaAutoConfig['useFlag'] == 'y' && $kakaoLunaAutoConfig['memberUseFlag'] == 'y') {
                            $smsDisapproval = $kakaoLunaAutoConfig['member']['JOIN']['smsDisapproval'];
                        } else if ($kakaoAutoConfig['useFlag'] == 'y' && $kakaoAutoConfig['memberUseFlag'] == 'y') {
                            $smsDisapproval = $kakaoAutoConfig['member']['JOIN']['smsDisapproval'];
                        } else {
                            $smsDisapproval = $smsAutoConfig['member']['JOIN']['smsDisapproval'];
                        }
                        StringUtils::strIsSet($smsDisapproval, 'n');
                        $sendSmsJoin = ($memberVO->getAppFl() == 'n' && $smsDisapproval == 'y') || $memberVO->getAppFl() == 'y';
                        $mailAutoConfig = ComponentUtils::getPolicy('mail.configAuto');
                        $mailDisapproval = $mailAutoConfig['join']['join']['mailDisapproval'];
                        StringUtils::strIsSet($smsDisapproval, 'n');
                        $sendMailJoin = ($memberVO->getAppFl() == 'n' && $mailDisapproval == 'y') || $memberVO->getAppFl() == 'y';
                        if ($sendSmsJoin) {
                            /** @var \Bundle\Component\Sms\SmsAuto $smsAuto */
                            $smsAuto = \App::load('\\Component\\Sms\\SmsAuto');
                            $smsAuto->notify();
                        }
                        if ($sendMailJoin) {
                            $member->sendEmailByJoin($memberVO);
                        }
                        if ($session->has('pushJoin')) {
                            $memNo = $memberVO->getMemNo();
                            $memberData = $member->getMember($memNo, 'memNo', 'memNo, memId, appFl, groupSno, mileage');
                            $coupon = new Coupon();
                            $getData = $coupon->getMemberSimpleJoinCouponList($memNo);
                            $member->setSimpleJoinLog($memNo, $memberData, $getData, 'push');
                            $session->del('pushJoin');
                        }
                        $member->setMemberDreamSessionStorage($memberVO->getMemNo(), $member->getDreamSessionStorage());
                    }
                    $sitelink = new SiteLink();
                    $returnUrl = $sitelink->link('../member/join_ok.php');

                    // 평생회원 이벤트
                    if ($request->post()->get('expirationFl') === '999') {
                        $modifyEvent = \App::load('\\Component\\Member\\MemberModifyEvent');
                        $memberData = $member->getMember($memberVO->getMemNo(), 'memNo', 'memNo, memNm, mallSno, groupSno'); // 회원정보
                        $resultLifeEvent = $modifyEvent->applyMemberLifeEvent($memberData, 'life');
                        if (empty($resultLifeEvent['msg']) == false) {
                            $msg = 'alert("' . $resultLifeEvent['msg'] . '");';
                        }
                    }
                    if ($wonderToken && $userProfile && $session->has(GodoWonderServerApi::SESSION_PARENT_URI)) {
                        $returnUrl = $session->get(GodoWonderServerApi::SESSION_PARENT_URI) . '../member/wonder_join_ok.php';
                        $this->js($msg . 'location.href=\'' . $returnUrl . '\';');
                    } else {
                        /**
                         * @date 2020-07-10 10:51:17 junlae.kim@webidas.com
                         * @see 본인인증 데이터 저장
                         */
                        if (Webidas::isInspect(true) && Webidas::on()) {
                            if ($request->post()->get('ssgdfmMode') == 'y' || $request->post()->get('staffCheck') == 'y') {
                                /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
                                $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');
                                $dataProxy->setMemberSync($memberVO->getMemNo(), 'ssgdfmFl', 'r');
                                if($request->post()->get('staffCheck') == 'y') {
                                    $dataProxy->setMemberSync($memberVO->getMemNo(), 'staffFl', 'r');
                                }

                                $this->js($msg . 'parent.location.href=\'' . $returnUrl . '\'');
                            } else {
                                $this->js($msg . 'parent.location.href=\'' . $returnUrl . '\'');
                            }
                        } else {
                            $this->js($msg . 'parent.location.href=\'' . $returnUrl . '\'');
                        }
                    }
                break;
                case 'overlapMemId':
                    try {
                        $memId = $request->post()->get('memId');

                        if (MemberUtil::overlapMemId($memId) === false) {
                            /**
                             * @date 2020-07-16 14:35:04
                             * @see for INSP
                             */
                            if (Webidas::isInspect(true) && Webidas::on()) {
                                $this->privateApprovalSnos = Member::getPrivateApprovalSnos();
                                $joinSession = new SimpleStorage(Session::get(Member::SESSION_JOIN_INFO));
                                if ($joinSession->get('privateApprovalOptionFl')[$this->privateApprovalSnos['option']] == 'y'
                                    && $joinSession->get('privateOfferFl')[$this->privateApprovalSnos['offer']] == 'y') {
                                    $serviceOption = ['GetDuplicate' => 'y'];
                                    $dreamSessionStorage = new SimpleStorage(Session::get(Member::SESSION_DREAM_SECURITY));
                                    /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
                                    $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');
                                    $formData = [
                                        'CI' => $dreamSessionStorage->get('CI'),
                                        'memId' => $memId
                                    ];
                                    $validate = $dataProxy->validate($formData, 'GetDuplicate', 'form');
                                    if (empty($validate) == true) {
                                        try {
                                            $sendData = $dataProxy->getSendFormData($formData);
                                            $ssgdfmClient = new SsgdfmClient();
                                            $ssgdfmClient->setEnvironment('develop');
                                            $ssgdfmClient->setMemNo(0);
                                            $result = $ssgdfmClient->callProxy($sendData, $serviceOption);
                                            //Webidas::dumper($ssgdfmClient->responseMsg, $result);
                                            //Webidas::stop();
                                            if ($result['duplicateFl'] == true) {
                                                throw new Exception(__("이미 등록된 아이디입니다.") . " " . __("다른 아이디를 입력해 주세요."));
                                            } else {
                                                if($result['memberFl']) { // 이미 회원
                                                    $this->json(__("사용가능한 아이디입니다."));
                                                } else { // 회원이 아니다.
                                                    $this->json(['code'=>'y', 'message'=>__("사용가능한 아이디입니다.")]);
                                                }
                                            }
                                        } catch (SsgdfmIndependentException $e) {
                                            $this->json(['code'=>'n', 'message'=>__("사용가능한 아이디입니다. 신세계면세점은 별도의 가입처리 필요합니다.")]);
                                        } catch (Exception $e) {
                                            $this->json(['code'=>'n', 'message'=>__("사용가능한 아이디입니다. 신세계면세점은 별도의 가입처리 필요합니다.")]);
                                        }
                                    } else {
                                        throw new Exception(__($validate['message']));
                                    }
                                }
                            }
                            $this->json(__("사용가능한 아이디입니다."));
                        } else {
                            throw new Exception(__("이미 등록된 아이디입니다.") . " " . __("다른 아이디를 입력해 주세요."));
                        }
                    } catch (Exception $e) {
                        throw $e;
                    }
                    break;
                case 'validateStaffNo':
                    /**
                     * @date 2020-07-16 14:35:04
                     * @see for INSP
                     */
                    if (Webidas::isInspect(true) && Webidas::on()) {

                        $this->privateApprovalSnos = Member::getPrivateApprovalSnos();
                        $joinSession = new SimpleStorage(Session::get(Member::SESSION_JOIN_INFO));

                        if ($joinSession->get('privateApprovalOptionFl')[$this->privateApprovalSnos['option']] == 'y'
                        && $joinSession->get('privateApprovalOptionFl')[$this->privateApprovalSnos['staffOption']] == 'y'
                        && $joinSession->get('privateOfferFl')[$this->privateApprovalSnos['offer']] == 'y'
                        ) {
                            //$joinSession = new SimpleStorage(Session::get(Member::SESSION_JOIN_INFO));
                            $dreamSessionStorage = new SimpleStorage(Session::get(Member::SESSION_DREAM_SECURITY));
                            $serviceOption = ['GetStaff' => 'y'];
                            /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
                            $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');
                            $formData = [
                                'CI' => $dreamSessionStorage->get('CI'),
                                'memId' => $request->post()->get('memId'),
                                'memNm' => $request->post()->get('memNm'),
                                'email' => $request->post()->get('email'),
                                'sexFl' => $request->post()->get('sexFl'),
                                'birthDt' => $dreamSessionStorage->get('ibirth'),
                                'cellPhone' => $request->post()->get('cellPhone'),
                                'staffNo' => $request->post()->get('staffNo')
                            ];
                            //Webidas::dumper($formData);
                            /*if(Webidas::isStrict()) {
                                $formData = [
                                    'CI' => $dreamSessionStorage->get('CI'),
                                    'memId' => $request->post()->get('memId'),
                                    'memNm' => '오승창',
                                    'email' => $request->post()->get('email'),
                                    'sexFl' => $request->post()->get('sexFl'),
                                    'birthDt' => 19860703,
                                    'cellPhone' => $request->post()->get('cellPhone'),
                                    'staffNo' => $request->post()->get('staffNo')
                                ];
                            }
                            */
                            $validate = $dataProxy->validate($formData, 'GetStaff', 'form');
                            //Webidas::dumper($validate);
                            //Webidas::stop();
                            if (empty($validate) == true) {
                                try {
                                    $sendData = $dataProxy->getSendFormData($formData);
                                    $ssgdfmClient = new SsgdfmClient();
                                    $ssgdfmClient->setEnvironment('develop');
                                    $ssgdfmClient->setMemNo(0);
                                    $result = $ssgdfmClient->callProxy($sendData, $serviceOption);
                                    //Webidas::dumper($ssgdfmClient->responseMsg);
                                    //Webidas::stop();
                                    if ($result['staffFl'] == true) {
                                        $this->json(['code'=>200, 'message'=>__("임직원 인증이 완료되었습니다.")]);
                                    } else {

                                        $this->json(['code' => 500, 'message' =>'임직원 정보가 없습니다. 다시 확인 부탁 드립니다.']);
                                    }
                                } catch (SsgdfmIndependentException $e) {
                                    $this->json(['code' => 500, 'message' =>'현재 서비스 점검중입니다.']);
                                } catch (Exception $e) {
                                    $this->json(['code' => 500, 'message' =>'현재 서비스 점검중입니다.']);
                                }
                            } else {
                                $this->json(['code' => 400, 'message' => $validate[0].' '.$validate[1]]);
                            }
                        }
                    }
                    break;
                case 'ssgdfm':
                    if (Webidas::isInspect(true) && Webidas::on()) {
                        /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
                        $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');


                        $sendFl = $dataProxy->getSendFl($request->post()->get('memNo'));

                        if ($sendFl['ssgdfmFl'] == 'r' || $sendFl['staffFl'] == 'r') {
                            $serviceName = $sendFl['ssgdfmFl'] == 'r' ? 'SetJoin' : 'SetStaff';
                            $sendData = $dataProxy->getSendData($request->post()->get('memNo'));
                            $serviceOption = [$serviceName => 'y'];
                            $validate = $dataProxy->validate($sendData, $serviceName, 'db');
                            if(empty($validate)==true) {
                                $ssgdfmClient = new SsgdfmClient();
                                $ssgdfmClient->setEnvironment('develop');
                                $ssgdfmClient->setMemNo($request->post()->get('memNo'));
                                $result = $ssgdfmClient->callProxy($sendData, $serviceOption);
                                //Webidas::dumper($ssgdfmClient->responseMsg, $result);
                                //Webidas::stop();
                                if($result['joinFl']) {
                                    $dataProxy->setMemberSync($request->post()->get('memNo'), 'ssgdfmFl', 'y');
                                }
                                if($sendFl['staffFl'] == 'r') {
                                    $dataProxy->setMemberSync($request->post()->get('memNo'), 'staffFl', 'y');
                                    $dataProxy->applyStaffGrade($request->post()->get('memNo'));
                                }
                                $this->json('연동완료');
                            } else {
                                if($sendFl['staffFl'] == 'r') {
                                    $dataProxy->setMemberSync($request->post()->get('memNo'), 'staffFl', 'y');
                                    $dataProxy->applyStaffGrade($request->post()->get('memNo'));
                                }
                                $this->json('항목 누락');
                            }
                        } else {
                            $this->json('연동없음');
                        }
                    } else {
                        $this->json('연동없음');
                    }
                break;
                default:
                    parent::index();
                break;
            }
        } catch (AlertRedirectException $e) {
            throw $e;
        } catch (AlertBackException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($request->isAjax() === true) {
                $logger->error($e->getTraceAsString());
                $this->json($this->exceptionToArray($e));
            } else {
                throw new AlertBackException($e->getMessage(), $e->getCode(), $e);
            }
        }

    }
}