<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-09
 * Time: 오후 5:35
 */

namespace Controller\Front\Member;

use Component\Webidas\Webidas;
use Session;
use Component\Member\Member;
use Framework\Object\SimpleStorage;


class JoinController extends \Bundle\Controller\Front\Member\JoinController
{

    /**
     * @var array
     * @date 2020-07-16 15:48:32 junlae.kim@webidas.com
     * @see 신세계면세점 회원가입(개인정보 수집및이용) 동의
     * @see 신세계면세점 회원가입을 위한 제3자정보제공동의
     */
    public $privateApprovalSnos = [];

    /**
     * {@inheritdoc}
     */
    public function index()
    {
        //$dreamSessionStorage = new SimpleStorage(Session::get(Member::SESSION_DREAM_SECURITY));

        //$dreamSessionStorage->
        //$joinSession = new SimpleStorage(Session::get(Member::SESSION_JOIN_INFO));
        parent::index();
        //$this->setData('isCBT', 'n');

        $this->setData('isCBT', Webidas::isInspect(true) ? 'y':'n');
        //Webidas::dumper(Webidas::isInspect(true));
        //Webidas::stop();
        if(Webidas::isInspect(true) && Webidas::on()) {
            $inheritData = $this->getData('data');

            if($inheritData['birthYear']=='' || $inheritData['birthMonth'] || $inheritData['birthDay']) {
                $inheritData['birthYear'] = substr($inheritData['birthDt'], 0, 4);
                $inheritData['birthMonth'] = substr($inheritData['birthDt'], 4, 2);
                $inheritData['birthDay'] = substr($inheritData['birthDt'], 6, 2);
            }

            $this->privateApprovalSnos = Member::getPrivateApprovalSnos();
            $joinSession = new SimpleStorage(Session::get(Member::SESSION_JOIN_INFO));
            if ($joinSession->get('privateApprovalOptionFl')[$this->privateApprovalSnos['option']] == 'y'
                && $joinSession->get('privateApprovalOptionFl')[$this->privateApprovalSnos['staffOption']] == 'y'
                && $joinSession->get('privateOfferFl')[$this->privateApprovalSnos['offer']] == 'y'
            ) {
                $this->setData('ssgdfm', ['staff' => 'y', 'member' => 'y']);
            } else if ($joinSession->get('privateApprovalOptionFl')[$this->privateApprovalSnos['option']] == 'y'
                && $joinSession->get('privateOfferFl')[$this->privateApprovalSnos['offer']] == 'y') {
                $this->setData('ssgdfm', ['staff' => 'n', 'member' => 'y']);
            } else {
                $this->setData('ssgdfm', ['staff' => 'n', 'member' => 'n']);
            }

            $inheritData['staffFl'] = 'n';
            $this->setData('data', $inheritData);


        }
        //Webidas::stop();
        //Webidas::dumper(Session::get(Member::SESSION_DREAM_SECURITY));
        //Webidas::stop();


    }
}