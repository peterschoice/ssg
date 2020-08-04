<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-13
 * Time: 오전 9:43
 */

namespace Controller\Front\Ssgdfm;


use Component\Webidas\Webidas;
use Request;
use Component\Webidas\SsgdfmClient;
use Exception;
use GuzzleHttp\Middleware;

class IndexController extends \Bundle\Controller\Front\Controller
{
    public function index()
    {
        try {

            if(!Webidas::isStrict()) {
                throw new Exception('ACCESS_DENIED');
            }
            /*
            $guzzleClient = new \GuzzleHttp\Client();
            $res = $guzzleClient->request('GET', 'http://devwww.ssgdfm.com/godomall/dupIdCheck');
            Webidas::dumper($res->getStatusCode(), $res->getHeader('content-type')[0], $res->getBody());
            Webidas::stop();
            */
            /** @var  \Component\Webidas\SsgdfmDataProxy $dataProxy */
            $dataProxy = \App::load('\\Component\\Webidas\\SsgdfmDataProxy');

            //Webidas::dumper($dataProxy->encrypt('webidas'));
            //Webidas::stop();
            //$string = 'webidas';
            //$encrypted = 'ZTE2Y2M0ZWY2YzVmY2FhZDZiZGUwYWM2YmFjZjkwMjk%3D';

            /**
             * @date 2020-07-10 11:04:23 junlae.kim@webidas.com
             * @see API전송 처리 특별한 사후액션은 없다.
             */
            $serviceOption = ['GetDuplicate'=>'n', 'GetMember'=>'n', 'GetStaff'=>'y', 'SetJoin'=>'n', 'SetStaff'=>'n'];

            if($serviceOption['GetDuplicate']=='y') {
                $sendData = $dataProxy->getSendData(
                    [
                        'CI'=>'KcMuy/OzCZeFuhMXcAocAtnM1J+Yrw1alP4mNuatFX4o2Jpyif4NaDVmyUCzSvkPbD2vSL3LYgyuyGDqlTzM3Q==',
                        'memId'=>'webidas'
                    ]
                );
            } else {
                $sendData = $dataProxy->getSendData(Request::get()->get('memNo'));
                $sendData['ciNo'] ='KcMuy/OzCZeFuhMXcAocAtnM1J+Yrw1alP4mNuatFX4o2Jpyif4NaDVmyUCzSvkPbD2vSL3LYgyuyGDqlTzM3Q==';
                $sendData['empNo'] = 162847; //'59846789'; // 162847
            }
            //Webidas::dumper($sendData);
            //Webidas::stop();

            $ssgdfmClient = new SsgdfmClient();
            $ssgdfmClient->setEnvironment('develop');
            // 20200716144834
            $ssgdfmClient->setMemNo(Request::get()->get('memNo'));
            $result = $ssgdfmClient->callProxy($sendData, $serviceOption);

            Webidas::dumper($ssgdfmClient->responseMsg, $result);
            Webidas::stop();
            /**
             * @date 2020-07-10 11:05:11 junlae.kim@webidas.com
             */
            /*
            if($result['staffFl']==true) {
                $dataProxy->applyStaffGrade(Request::get()->get('memNo'));
            }
            if($result['joinFl']==true) {
                $dataProxy->setMemberSync(Request::get()->get('memNo'));
            }
            */
        } catch (\Throwable $e) {
            //throw $e;
            Webidas::dumper($e);
        }
        exit;
    }
}