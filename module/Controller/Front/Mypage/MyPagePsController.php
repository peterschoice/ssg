<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-17
 * Time: 오후 1:51
 */

namespace Controller\Front\Mypage;

use Component\Webidas\SsgdfmException\SsgdfmApiException;
use Exception;
use Framework\Debug\Exception\AlertOnlyException;
use Request;
use Component\Webidas\Webidas;
use Component\Member\Member;
use Session;
use Component\Member\MyPage;
use Component\Webidas\SsgdfmClient;
use Bundle\Component\Member\History;
use Bundle\Component\SiteLink\SiteLink;
use App;
use Component\Webidas\SsgdfmException\SsgdfmIndependentException;

class MyPagePsController extends \Bundle\Controller\Front\Mypage\MyPagePsController
{

    public $privateApprovalSnos = [];

    public function index()
    {
        try {
            /** @var  \Component\Member\MyPage $myPage */
            $myPage = \App::load('\\Component\\Member\\MyPage');
            $mode = Request::post()->get('mode', '');

            switch($mode) {
                case 'modify':
                    $beforeSession = Session::get(Member::SESSION_MEMBER_LOGIN);
                    $requestParams = Request::post()->xss()->all();
                    //Webidas::dumper($requestParams);
                    //회원 번호는 세션에 저장 되어 있는 회원 번호로 가져옴
                    $requestParams['memNo'] = Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO, 0);
                    $beforeMemberInfo = $myPage->getDataByTable(DB_MEMBER, $requestParams['memNo'], 'memNo');
                    $beforeSession['recommId'] = $beforeMemberInfo['recommId'];
                    $beforeSession['recommFl'] = $beforeMemberInfo['recommFl'];

                    // 회원정보 이벤트
                    $modifyEvent = \App::load('\\Component\\Member\\MemberModifyEvent');
                    $mallSno = \SESSION::get(SESSION_GLOBAL_MALL)['sno'] ? \SESSION::get(SESSION_GLOBAL_MALL)['sno'] : DEFAULT_MALL_NUMBER;
                    $activeEvent = $modifyEvent->getActiveMemberModifyEvent($mallSno, 'life');
                    $memberLifeEventCnt = $modifyEvent->checkDuplicationModifyEvent($activeEvent['sno'], $requestParams['memNo'], 'life'); // 이벤트 참여내역
                    $getMemberLifeEventCount = $modifyEvent->getMemberLifeEventCount($requestParams['memNo']); // 평생회원 변경이력
                    //Webidas::dumper($requestParams);

                    try {
                        Session::set(Member::SESSION_MODIFY_MEMBER_INFO, $beforeMemberInfo);
                        \DB::begin_tran();
                        $myPage->modify($requestParams, $beforeSession);
                        $history = new History();
                        $history->setMemNo($requestParams['memNo']);
                        $history->setProcessor('member');
                        $history->setProcessorIp(Request::getRemoteAddress());
                        $history->initBeforeAndAfter();
                        $history->addFilter(array_keys($requestParams));
                        $history->writeHistory();
                        \DB::commit();
                    } catch (Exception $e) {
                        \DB::rollback();
                        throw $e;
                    }

                    $myPage->sendEmailByPasswordChange($requestParams, Session::get(Member::SESSION_MEMBER_LOGIN));
                    $myPage->sendSmsByAgreementFlag($beforeSession, Session::get(Member::SESSION_MEMBER_LOGIN));

                    // 회원정보 수정 이벤트
                    $afterSession = Session::get(Member::SESSION_MEMBER_LOGIN);
                    if (strtotime($afterSession['changePasswordDt']) > strtotime($beforeSession['changePasswordDt'])) {
                        $requestParams['changePasswordFl'] = 'y';
                    }
                    $resultModifyEvent = $modifyEvent->applyMemberModifyEvent($requestParams, $beforeMemberInfo);
                    if (empty($resultModifyEvent['msg']) == false) {
                        $msg = 'alert("' . $resultModifyEvent['msg'] . '");';
                    }

                    // 평생회원 이벤트
                    if (!$memberLifeEventCnt && $getMemberLifeEventCount == 0 && $requestParams['expirationFl'] === '999') {
                        $resultLifeEvent = $modifyEvent->applyMemberLifeEvent($beforeMemberInfo, 'life');
                        if (empty($resultLifeEvent['msg']) == false) {
                            $msg = 'alert("' . $resultLifeEvent['msg'] . '");';
                        }
                    }

                    /**
                     * @date 2020-07-10 10:51:17 junlae.kim@webidas.com
                     * @see 본인인증 데이터 저장상태
                     */
                    if (Webidas::isInspect(true) && Webidas::on()) {
                        if ($requestParams['staffCheck'] == 'y') {
                            /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
                            $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');
                            $dataProxy->setMemberSync($requestParams['memNo'], 'staffFl', 'r');
                        }
                    }

                    $sitelink = new SiteLink();
                    $returnUrl = $sitelink->link(Request::getReferer());

                    // 회원연동처리

                    $this->js('alert(\'' . __('회원정보 수정이 성공하였습니다.') . '\');' . $msg . 'parent.location.href=\'' . $returnUrl . '\'');

                    break;
                case 'validateStaffNo':
                    /**
                     * @date 2020-07-16 14:35:04
                     * @see for INSP
                     */
                    if (Webidas::isInspect(true) && Webidas::on()) {
                        $serviceOption = ['GetStaff' => 'y'];
                        /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
                        $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');
                        $sendData = $dataProxy->getSendData(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO));
                        $sendData['empNo'] = Request::post()->get('staffNo');
                        if(Webidas::isStrict()) {
                            $sendData['userName'] =  '오승창';
                            $sendData['birthDt'] = 19860703;
                            $sendData['empNo'] = 181765;
                        }

                        if($sendData['ciNo']=='') {
                            $sendData['ciNo'] = 'CI';
                        }

                        $validate = $dataProxy->validate($sendData, 'GetStaff', 'db');
                        if (empty($validate) == true) {
                            try {
                                $ssgdfmClient = new SsgdfmClient();
                                $ssgdfmClient->setEnvironment('develop');
                                $ssgdfmClient->setMemNo(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO));
                                $result = $ssgdfmClient->callProxy($sendData, $serviceOption);

                                //Webidas::dumper($result);
                                //Webidas::stop();
                                //$result['staffFl'] = true;

                                if ($result['staffFl'] == true) {
                                    $dataProxy->setMemberSync(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO), 'staffFl', 'r');
                                    $this->json(['code' => 200, 'message' => __("임직원 인증이 완료되었습니다.")]);
                                } else {

                                    $this->json(['code' => 500, 'message' => '임직원 정보가 없습니다. 다시 확인 부탁 드립니다.']);
                                }
                            } catch (SsgdfmIndependentException $e) {
                                //$this->json(['code' => 500, 'message' =>'현재 서비스 점검중입니다.']);
                                $this->json(['code' => 200, 'message' => __("임직원 인증이 완료되었습니다.")]);
                            } catch (Exception $e) {
                                $this->json(['code' => 500, 'message' =>'현재 서비스 점검중입니다.']);
                            }
                        } else {
                            $this->json(['code' => 400, 'message' => $validate[0].' '.$validate[1]]);
                        }
                    }
                    break;
                case 'setStaff':

                    if (Webidas::isInspect(true) && Webidas::on()) {
                        /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
                        $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');
                        $sendData = $dataProxy->getSendData(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO));
                        if(Webidas::isStrict()) {
                            $sendData['userName'] =  '오승창';
                            $sendData['birthDt'] = 19860703;
                            $sendData['empNo'] = 181765;
                        }
                        $serviceName = 'SetStaff';

                        if($sendData['ciNo']=='') {
                            $sendData['ciNo'] = 'CI';
                        }

                        $serviceOption = [$serviceName => 'y'];
                        $validate = $dataProxy->validate($sendData, $serviceName, 'db');
                        if(empty($validate)==true) {
                            $ssgdfmClient = new SsgdfmClient();
                            $ssgdfmClient->setEnvironment('develop');
                            $ssgdfmClient->setMemNo(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO));
                            $result = $ssgdfmClient->callProxy($sendData, $serviceOption);
                            //Webidas::dumper($ssgdfmClient->responseMsg, $result);
                            //Webidas::stop();
                            if($result['staffJoinFl']) {
                                $dataProxy->setMemberSync(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO), 'staffFl', 'y');
                                $dataProxy->applyStaffGrade(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO));

                                $this->json('연동완료');
                            } else {
                                $dataProxy->setMemberSync(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO), 'staffFl', 'y');
                                $dataProxy->applyStaffGrade(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO));

                                $this->json('연동실패');
                            }
                       } else {
                            $dataProxy->setMemberSync(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO), 'staffFl', 'y');
                            $dataProxy->applyStaffGrade(Session::get(MyPage::SESSION_MY_PAGE_MEMBER_NO));

                            $this->json('항목 누락');
                        }

                    } else {
                        $this->json('연동없음');
                    }
                    break;
                default:
                    parent::index();
                break;
            }
        } catch (AlertOnlyException $e) {
            throw $e;
        } catch (Exception $e) {
            if (Request::isAjax()) {
                $this->json($this->exceptionToArray($e));
            } else {
                throw new AlertOnlyException($e->getMessage());
            }
        }

    }
}