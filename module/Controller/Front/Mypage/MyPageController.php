<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-17
 * Time: 오전 9:59
 */

namespace Controller\Front\Mypage;

use Component\Webidas\Webidas;
use Framework\Debug\Exception\AlertBackException;
use Exception;
use Component\Member\Member;
use Bundle\Component\Member\MyPage;
use Session;

class MyPageController extends \Bundle\Controller\Front\Mypage\MyPageController
{

    protected $privateApprovalSnos=[];


    public function index()
    {
        try {

            //Webidas::stop();
            parent::index();
            if (Webidas::isInspect(true) && Webidas::on()) {
                $inheritData = $this->getData('data');
                $this->setData('ssgdfm', ['staff'=>$inheritData['staffFl']=='y' ? 'n':'y']);
                $this->privateApprovalSnos = Member::getPrivateApprovalSnos();
                $this->setData('privateApprovalSnos', json_encode($this->privateApprovalSnos));
                $this->setData('isCBT', 'y');
            } else {
                $this->setData('isCBT', 'n');
            }

        } catch (AlertBackException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AlertBackException($e->getMessage(), $e->getCode(), $e);
        }
    }
}