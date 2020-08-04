<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-19
 * Time: 오후 6:05
 */

namespace Controller\Mobile\Member;

use App;
use Exception;
use Request;
use Framework\Debug\Exception\AlertBackException;
use Bundle\Component\Member\Util\MemberUtil;
use Component\Webidas\Webidas;
use Component\Member\Member;
use Framework\Object\SimpleStorage;
use Session;
use Component\Webidas\SsgdfmClient;
use Component\Webidas\SsgdfmException\SsgdfmIndependentException;
use Bundle\Component\Godo\GodoWonderServerApi;
use Bundle\Component\Godo\GodoPaycoServerApi;
use Bundle\Component\Facebook\Facebook;
use Bundle\Component\Godo\GodoNaverServerApi;
use Bundle\Component\Member\MemberSnsService;
use Bundle\Component\Godo\GodoKakaoServerApi;
use Framework\Utility\ComponentUtils;
use Framework\Utility\StringUtils;
use Bundle\Component\Policy\SnsLoginPolicy;
use Bundle\Component\Coupon\Coupon;

class MemberPsController extends \Bundle\Controller\Mobile\Member\MemberPsController
{

    public $privateApprovalSnos = [];


    public function index()
    {
        try {
            if (Request::isMyapp() === true && Request::post()->get('hash')) {
                $myApp = App::load('\\Component\\Myapp\\Myapp');
                $code = Request::post()->get('code');
                if ($code) {
                    $guestOrderInfo = $myApp->getGuestOrderInfo($code);
                    Request::post()->set('orderNm', $guestOrderInfo['orderNm']);
                    Request::post()->set('orderNo', $guestOrderInfo['orderNo']);
                } else if (Request::post()->get('hash')) {
                    $postData = Request::post()->all();
                    if ($myApp->hmacValidate($postData) !== true) {
                        \Logger::channel('myapp')->error('Wrong Hash : ' . json_encode(Request::post()->all()));
                        throw new Exception(__("요청을 찾을 수 없습니다."));
                    }
                }
            } else {
                if((Request::getReferer() == Request::getDomainUrl()) || empty(Request::getReferer()) === true){
                    \Logger::error(__METHOD__ .' Access without referer');
                    throw new Exception(__("요청을 찾을 수 없습니다."));
                }
            }
            /** @var  \Component\Member\Member $member */
            $member = App::load('\\Component\\Member\\Member');

            // --- 수신 정보
            $returnUrl = urldecode(Request::post()->get('returnUrl'));
            if (empty($returnUrl) || strpos($returnUrl, "member_ps") !== false) {
                $returnUrl = Request::getReferer();
            }

            $mode = Request::post()->get('mode', Request::get()->get('mode'));
            switch ($mode) {

                // 회원가입
                case 'join':
                    $memberVO = null;
                    try {
                        if (Session::has(GodoWonderServerApi::SESSION_USER_PROFILE)) {
                            \Component\Member\Util\MemberUtil::saveJoinInfoBySession(Request::post()->all());
                        }

                        $joinSessionForAfterAction = new SimpleStorage(Session::get(Member::SESSION_JOIN_INFO));

                        \DB::begin_tran();
                        Session::set('isFront', 'y');
                        if (Session::has('pushJoin')) {
                            Request::post()->set('simpleJoinFl','push');
                        }
                        $memberVO = $member->join(Request::post()->xss()->all());
                        Session::del('isFront');
                        if (Session::has(GodoPaycoServerApi::SESSION_USER_PROFILE)) {
                            $paycoToken = Session::get(GodoPaycoServerApi::SESSION_ACCESS_TOKEN);
                            $userProfile = Session::get(GodoPaycoServerApi::SESSION_USER_PROFILE);
                            Session::del(GodoPaycoServerApi::SESSION_ACCESS_TOKEN);
                            Session::del(GodoPaycoServerApi::SESSION_USER_PROFILE);
                            $memberSnsService = new MemberSnsService();
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $paycoToken['idNo'], $paycoToken['access_token'], 'payco');
                            $paycoApi = new GodoPaycoServerApi();
                            $paycoApi->logByJoin();
                        } elseif (Session::has(Facebook::SESSION_USER_PROFILE)) {
                            $userProfile = Session::get(Facebook::SESSION_USER_PROFILE);
                            $accessToken = Session::get(Facebook::SESSION_ACCESS_TOKEN);
                            $memberSnsService = new MemberSnsService();
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $userProfile['id'], $accessToken, SnsLoginPolicy::FACEBOOK);
                        } elseif (Session::has(GodoNaverServerApi::SESSION_USER_PROFILE)) {
                            $naverToken = Session::get(GodoNaverServerApi::SESSION_ACCESS_TOKEN);
                            $userProfile = Session::get(GodoNaverServerApi::SESSION_USER_PROFILE);
                            Session::del(GodoNaverServerApi::SESSION_ACCESS_TOKEN);
                            Session::del(GodoNaverServerApi::SESSION_USER_PROFILE);
                            $memberSnsService = new MemberSnsService();
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $userProfile['id'], $naverToken['access_token'], 'naver');
                            $naverApi = new GodoNaverServerApi();
                            $naverApi->logByJoin();
                        } elseif(Session::has(GodoKakaoServerApi::SESSION_USER_PROFILE)) {
                            $kakaoToken = Session::get(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                            $kakaoProfile = Session::get(GodoKakaoServerApi::SESSION_USER_PROFILE);
                            Session::del(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                            Session::del(GodoKakaoServerApi::SESSION_USER_PROFILE);
                            $memberSnsService = new MemberSnsService();
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $kakaoProfile['id'], $kakaoToken['access_token'], 'kakao');
                        } elseif (Session::has(GodoWonderServerApi::SESSION_USER_PROFILE)) {
                            $wonderToken = Session::get(GodoWonderServerApi::SESSION_ACCESS_TOKEN);
                            $userProfile = Session::get(GodoWonderServerApi::SESSION_USER_PROFILE);
                            Session::del(GodoWonderServerApi::SESSION_ACCESS_TOKEN);
                            Session::del(GodoWonderServerApi::SESSION_USER_PROFILE);
                            $memberSnsService = new MemberSnsService();
                            $memberSnsService->joinBySns($memberVO->getMemNo(), $userProfile['mid'], $wonderToken['access_token'], 'wonder');
                            $wonderApi = new GodoWonderServerApi();
                            $wonderApi->logByJoin();
                        }
                        \DB::commit();
                    } catch (Exception $e) {
                        \DB::rollback();
                        if (get_class($e) == Exception::class) {
                            if ($e->getMessage()) {
                                $this->js("alert('".$e->getMessage()."');window.parent.callback_not_disabled();");
                            }
                        } else {
                            throw $e;
                        }
                    }
                    if ($memberVO != null) {
                        $smsAutoConfig = ComponentUtils::getPolicy('sms.smsAuto');
                        $kakaoAutoConfig = ComponentUtils::getPolicy('kakaoAlrim.kakaoAuto');
                        $kakaoLunaAutoConfig = ComponentUtils::getPolicy('kakaoAlrimLuna.kakaoAuto');
                        if (gd_is_plus_shop(PLUSSHOP_CODE_KAKAOALRIMLUNA) === true && $kakaoLunaAutoConfig['useFlag'] == 'y' && $kakaoLunaAutoConfig['memberUseFlag'] == 'y') {
                            $smsDisapproval = $kakaoLunaAutoConfig['member']['JOIN']['smsDisapproval'];
                        }else if ($kakaoAutoConfig['useFlag'] == 'y' && $kakaoAutoConfig['memberUseFlag'] == 'y') {
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
                        if (Session::has('pushJoin')) {
                            $memNo = $memberVO->getMemNo();
                            $memberData = $member->getMember($memNo, 'memNo', 'memNo, memId, appFl, groupSno, mileage');
                            $coupon = new Coupon();
                            $getData = $coupon->getMemberSimpleJoinCouponList($memNo);
                            $member->setSimpleJoinLog($memNo, $memberData, $getData, 'push');
                            Session::del('pushJoin');
                        }
                        /**
                         * @date 2020-07-10 10:51:17 junlae.kim@webidas.com
                         * @see 본인인증 데이터 저장
                         */
                        $member->setMemberDreamSessionStorage($memberVO->getMemNo(), $member->getDreamSessionStorage());
                    }

                    // 에이스카운터 회원가입 스크립트
                    $acecounterScript = \App::load('\\Component\\Nhn\\AcecounterCommonScript');
                    $acecounterUse = $acecounterScript->getAcecounterUseCheck();
                    if ($acecounterUse) {
                        echo $acecounterScript->getJoinScript($memberVO->getMemNo());
                    }


                    // 평생회원 이벤트
                    if (Request::post()->get('expirationFl') === '999') {
                        $modifyEvent = \App::load('\\Component\\Member\\MemberModifyEvent');
                        $memberData = $member->getMember($memberVO->getMemNo(), 'memNo', 'memNo, memNm, mallSno, groupSno'); // 회원정보
                        $resultLifeEvent = $modifyEvent->applyMemberLifeEvent($memberData, 'life');
                        if (empty($resultLifeEvent['msg']) == false) {
                            $msg = 'alert("' . $resultLifeEvent['msg'] . '");';
                        }
                    }

                    if (Webidas::isInspect(true) && Webidas::on()) {
                        if (Request::post()->get('ssgdfmMode') == 'y' || Request::post()->get('staffCheck') == 'y') {
                            /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
                            $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');
                            $dataProxy->setMemberSync($memberVO->getMemNo(), 'ssgdfmFl', 'r');
                            if (Request::post()->get('staffCheck') == 'y') {
                                $dataProxy->setMemberSync($memberVO->getMemNo(), 'staffFl', 'r');
                            }
                        }
                    }
                    $this->js($msg. 'parent.location.href=\'../member/join_ok.php\'');
                    break;
                // 아이디중복확인
                case 'overlapMemId':
                    $memId = Request::post()->get('memId');
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
                                'memId' => Request::post()->get('memId'),
                                'memNm' => Request::post()->get('memNm'),
                                'email' => Request::post()->get('email'),
                                'sexFl' => Request::post()->get('sexFl'),
                                'birthDt' => $dreamSessionStorage->get('ibirth'),
                                'cellPhone' => Request::post()->get('cellPhone'),
                                'staffNo' => Request::post()->get('staffNo')
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
                        $sendFl = $dataProxy->getSendFl(Request::post()->get('memNo'));

                        if ($sendFl['ssgdfmFl'] == 'r' || $sendFl['staffFl'] == 'r') {
                            $serviceName = $sendFl['ssgdfmFl'] == 'r' ? 'SetJoin' : 'SetStaff';
                            $sendData = $dataProxy->getSendData(Request::post()->get('memNo'));
                            $serviceOption = [$serviceName => 'y'];
                            $validate = $dataProxy->validate($sendData, $serviceName, 'db');
                            if(empty($validate)==true) {
                                $ssgdfmClient = new SsgdfmClient();
                                $ssgdfmClient->setEnvironment('develop');
                                $ssgdfmClient->setMemNo(Request::post()->get('memNo'));
                                $result = $ssgdfmClient->callProxy($sendData, $serviceOption);
                                //Webidas::dumper($ssgdfmClient->responseMsg, $result);
                                //Webidas::stop();

                                if($result['joinFl']) {
                                    $dataProxy->setMemberSync(Request::post()->get('memNo'), 'ssgdfmFl', 'y');
                                }
                                if($sendFl['staffFl'] == 'r') {
                                    $dataProxy->setMemberSync(Request::post()->get('memNo'), 'staffFl', 'y');
                                    $dataProxy->applyStaffGrade(Request::post()->get('memNo'));
                                }
                                $this->json('연동완료');
                            } else {
                                if($sendFl['staffFl'] == 'r') {
                                    $dataProxy->setMemberSync(Request::post()->get('memNo'), 'staffFl', 'y');
                                    $dataProxy->applyStaffGrade(Request::post()->get('memNo'));
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
        } catch (AlertBackException $e) {
            throw $e;
        } catch (Exception $e) {
            \Logger::error(__METHOD__ . ', ' . $e->getFile() . '[' . $e->getLine() . '], ' . $e->getMessage(), $e->getTrace());
            if (Request::isAjax() === true) {
                $this->json($this->exceptionToArray($e));
            } else {
                throw new AlertBackException($e->getMessage());
            }
        }
    }
}