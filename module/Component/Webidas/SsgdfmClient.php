<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-09
 * Time: 오후 5:32
 */

namespace Component\Webidas;


use Component\Webidas\SsgdfmException\SsgdfmApiException;
use Component\Webidas\SsgdfmException\SsgdfmBuildException;
use Component\Webidas\SsgdfmException\SsgdfmCurlException;
use Component\Webidas\SsgdfmException\SsgdfmHttpException;
use Component\Webidas\SsgdfmException\SsgdfmIndependentException;
use Framework\Utility\StringUtils;
use Exception;

class SsgdfmClient
{
    protected $requestBase = [
        //'develop'=>'http://devwww.ssgdfm.com/godomall/',
        'develop'=>'https://qawww.ssgdfm.com/godomall/',
        'product'=>'https://www.ssgdfm.com/godomall/'
    ];

    /** @var array  */
    protected $endPoints =[
        'GetMember'=>'search',
        'GetStaff'=>'search',
        'SetJoin'=>'join',
        'GetDuplicate'=>'dupIdCheck',
        'SetStaff'=>'updSsgEmp'
    ];

    /** @var string */
    protected $serviceUrl;

    /** @var resource */
    protected $client;

    /** @var  mixed */
    protected $error;

    protected $_typeMap;

    protected $_response;

    protected $_hasError = false;

    protected $_debug = false;

    public function setDebug($bool = false) {
        $this->_debug = $bool;
    }
    public function getDebug() {
        return $this->_debug;
    }

    protected $environment = 'develop'; // product or develop

    public function setEnvironment($environment = 'develop') {


        if(Webidas::isDeployDate()) {
            $this->environment = 'product';
        } else {
            if(Webidas::isInspect()) {
                $this->environment = $environment;
            } else {
                $this->environment = 'develop';
            }
        }
    }

    public function getEnvironment() {

        return $this->environment;
    }

    /**
     * @var array
     * @see -H "X-API-KEY : 당사가 제공하는 API Key"
     * @see AES128bit 사용 : Key, IV 값 : MD5 hash 화 처리 -> Base64 Encode -> ULR Encode
     */
    protected $appKey = [
        /*'develop'=>[
            'GetMember'=>'A411EB240A2A13725FB6CA3089E51C10',
            'GetStaff'=>'A411EB240A2A13725FB6CA3089E51C10',
            'SetJoin'=>'ADB2986C95E15E50CDFD7D7ADA25D6F2',
            'GetDuplicate'=>'B79E983D47C03D1E17884F897AB39EA'
        ],*/
        'develop'=>[
            'GetMember'=>'A411EB240A2A13725FB6CA3089E51C10',
            'GetStaff'=>'A411EB240A2A13725FB6CA3089E51C10',
            'SetJoin'=>'ADB2986C95E15E50CDFD7D7ADA25D6F2',
            'GetDuplicate'=>'5B79E983D47C03D1E17884F897AB39EA',
            'SetStaff'=>'EE34EC1EC19553A4526D8C3CABDF5EEE'
        ],

        'product'=>[
            'GetMember'=>'A411EB240A2A13725FB6CA3089E51C10',
            'GetStaff'=>'A411EB240A2A13725FB6CA3089E51C10',
            'SetJoin'=>'ADB2986C95E15E50CDFD7D7ADA25D6F2',
            'GetDuplicate'=>'5B79E983D47C03D1E17884F897AB39EA',
            'SetStaff'=>'EE34EC1EC19553A4526D8C3CABDF5EEE'
        ]
    ];

    /**
     * @param mixed $index
     * @return mixed
     */
    public function getAppKey($index = null)
    {
        if(is_null($index)) {
            return $this->appKey[$this->getEnvironment()];
        } else {
            return $this->appKey[$this->getEnvironment()][$index];
        }

    }

    /**
     * @param mixed $appKey
     * @param mixed $index
     */
    public function setAppKey($appKey, $index = null)
    {
        if(is_null($index)) {
            $this->appKey[$this->getEnvironment()] = $appKey;
        } else {
            $this->appKey[$this->getEnvironment()][$index] = $appKey;
        }
    }

    public function __construct() {
    }

    /** @var SsgdfmLogger */
    protected $logger;

    public function setLogger(SsgdfmLogger $logger) {
        $this->logger = $logger;
    }

    /**
     * @date 2020-07-10 11:53:54 junlae.kim@webidas.com
     * @see request는 format상관없이 외부에서 구성
     * @see format을 여기서 구성
     */
    /**
     * @param BaseRequestType|GetMemberRequestType|SetJoinRequestType|GetStaffRequestType|GetDuplicateRequestType $request
     * @param $format
     * @return mixed
     */
    protected function build($request, $format='json') {
        $getData = [];
        foreach($request->getMetaDataElements() as $typeName=>$typeInfo) {
            $method = "get".SsgdfmClient::convertToCamelCase($typeName);
            if($typeInfo['type']=='string') {
                $getData[$typeName] = strval($request->{$method}());
            } else {
                $getData[$typeName] = $request->{$method}();
            }

        }
        return json_encode($getData, JSON_UNESCAPED_UNICODE);
    }

    public function convertRuleValidator($elementName, $data) {
        switch($elementName) {
            case 'hpTellNum':
                return StringUtils::numberToCellPhone(str_replace('-', '', $data));
            case 'sexGbn':
                $rule = ['m'=>'M','w'=>'F'];
                return $rule[$data];
            case 'birthDt':
                return str_replace('-', '', $data);
            case 'userId':
                return strtoupper($data);
            case 'mailRecvYn':
            case 'smsRecvYn':
                return strtoupper($data);
            default:
                return $data;
        }
    }

    /**
     * @param string $serviceName
     * @return BaseRequestType|GetMemberRequestType|SetJoinRequestType|GetStaffRequestType|GetDuplicateRequestType
     */
    public function getServiceTypeClass($serviceName) {
        $fullyQualifiedClassName = 'Component\Webidas\\'.$serviceName."RequestType";
        /** @var BaseRequestType|GetMemberRequestType|SetJoinRequestType|GetStaffRequestType|GetDuplicateRequestType $request */
        return  new $fullyQualifiedClassName();
    }


    public function getApiCodeType($serviceName) {

        switch ($serviceName) {
            case 'SetJoin':
                return 'J';
            case 'GetStaff':
                return 'E';
            case 'SetStaff':
                return 'S';
            case 'GetMember':
                return 'D';
            case 'GetDuplicate':
                return 'I';
            default:
                return 'I';
        }
    }


    /**
     * @var int LOG에 넣기 위해서 별도의 프로퍼티로 할당한다
     */
    protected $memNo = 0;

    public function setMemNo($memNo) {
        $this->memNo = $memNo;
    }

    public function getMemNo() {
        return $this->memNo;
    }


    /**
     * @param string $serviceName
     * @param array $arrData DB 컬럼 원본데이터를 API elelentName 에 맞게 가져옴
     * @throws Exception|\TypeError|\Error
     * @return BaseRequestType|GetMemberRequestType|SetJoinRequestType|GetStaffRequestType|GetDuplicateRequestType|null
     */
    public function getRequest($serviceName, $arrData = null) {

        //Webidas::dumper($arrData);


        $arrData['transaction_id'] = $this->generateTransactionId();
        $arrData['apiCode'] = $this->getApiCodeType($serviceName);

        $request = $this->getServiceTypeClass($serviceName);
        $metaData = $request->getMetaDataElements();
        foreach($metaData as $metaName=>$metaInfo) {
            if ($metaInfo['required'] && gd_isset($arrData[$metaName], false) === false) {
                throw new Exception($metaName . ' 데이터는 필수입니다.', 100);
            }
            $methodName = 'set' . SsgdfmClient::convertToCamelCase($metaName);
            if (!method_exists($request, $methodName)) {
                throw new Exception($metaName . ' 메소드가 누락되어있습니다.', 110);
            }
            $request->$methodName(SsgdfmClient::convertRuleValidator($metaName, $arrData[$metaName]));
        }
        return $request;
    }

    protected function getRequestHeader($serviceName) {
        return [
            //"Accept: application/json",
            "Content-Type: application/json; charset=UTF-8",
            //'User-Agent: GodoTopfunmall/1.0',
            //"Content-Length : ". strlen($jsonRequest),
            //'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-API-KEY: '.$this->getAppKey($serviceName)
        ];
    }

    public $responseMsg = null;

    /**
     * @param $serviceName
     * @param BaseRequestType $request
     * @return bool|BaseResponseType
     * @throws Exception
     */
    public function call($serviceName, $request) {

        // 'Accept: application/json',
        //
        // Setting the HTTP method to POST defaults to content type following
        // 'Content-Type: application/x-www-form-urlencoded'
        // https://stackoverflow.com/questions/16079754/
        //Webidas::dumper(self::build($request));
        //$response = false;
        //Webidas::dumper($this->getEnvironment());
        /*if($this->getEnvironment() == 'develop') {
            //ob_start();
            $output = fopen('php://temp', 'w+');
        }*/
        //header('Content-Type: application/x-www-form-urlencoded');
        $buildRequest= self::build($request);

        //Webidas::dumper($buildRequest);
        //Webidas::stop();

        //Webidas::dumper($this->getRequestHeader($serviceName));
        $this->client = curl_init();
        $this->setHttpHeader($this->getRequestHeader($serviceName))
            ->setPost(true)
            ->setPostFields($buildRequest)
            ->setSslVerifyPeer(0)
            ->setSslVerifyHost(0)
            ->setReturnTransfer()
            ->setVerbose($this->getEnvironment()=='develop' ? true : false)
            ->setInfoHeaderOut($this->getEnvironment()=='develop' ? true : false)
            //->setSslVersion()
            ->setServiceUrl($serviceName)
            //->setFollowLocation()
            ->setConnectTimeout()
            ->setTimeout();

            /*if($this->getEnvironment()=='develop') {
                $this->setStdErr($output);
            }*/
        /*->setFollowLocation();*/

        $response = curl_exec($this->client);




        //Webidas::dumper($response);
        //Webidas::stop();

        /*if($this->getEnvironment() == 'develop') {
            Webidas::dumper(self::getCurlInfo(), curl_error($this->client), $buildRequest);
            $this->getStdErr($output);
            fclose($output);
            Webidas::stop();
        }*/
        //Webidas::dumper(self::getCurlInfo(), self::setCurlError());
        $this->responseMsg = $response;
        //Webidas::dumper($this->caInfo);
        //var_dump(self::getCurlInfo());
        //Webidas::dumper(11111);
        //Webidas::dumper(self::getCurlInfo(), $response);
        //Webidas::stop();
        //Webidas::stop();
        //Webidas::stop();
        //Webidas::dumper(self::getError());
        //Webidas::dumper($response,self::build($request),$request);
        //Webidas::dumper($response);
        //Webidas::stop();

        //Webidas::dumper($response);

        if($response === false) {
            self::setCurlError();
            $ret = false;
        } else {
            $response_code = (int)self::getCurlInfo('http_code');
            //Webidas::dumper(self::getCurlInfo());

            //Webidas::dumper($response_code);
            //Webidas::stop();

            if($this->getDebug()==false) {
                $firstCode = ceil($response_code/100);
            } else {
                $firstCode = 2;
            }
            switch($firstCode) {
                case 3:
                case 2:
                    $ret = self::parseResponse($serviceName, $response);
                    //Webidas::dumper($ret);
                    //Webidas::stop();
                    break;
                case 4:
                case 5:
                default:
                    $this->_hasError = true;
                    self::setHttpError($response_code);
                    $ret = false;
                    break;
            }
        }
        curl_close($this->client);
        return $ret;
    }

    public function generateTransactionId() {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * @param $service
     * @param BaseRequestType|GetMemberRequestType|SetJoinRequestType|GetStaffRequestType|GetDuplicateRequestType $request
     * @return mixed
     * @throws
     */
    public function callSingle($service, $request) {
        try {
            $logSeq = $this->logger->beforePreLog($request->getTransactionId(), $service);
            if ($logSeq == 0) {
                throw new SsgdfmBuildException($request->getTransactionId(), SsgdfmBuildException::TRANSACTION_DUPLICATE);
            }
            $this->logger->saveLog(['memNo' => $this->getMemNo(), 'sendData' => self::build($request)], $logSeq);
            //Webidas::dumper($logContainer, $request);
            //Webidas::stop();
            $response = self::call($service, $request);
            $this->logger->saveLog(['receiveData' => $this->responseMsg, 'sendDate' => date('Y-m-d H:i:s')], $logSeq);
            if (!$this->hasError()) {
                return $response;
            } else {
                if ($this->getCurlError()) {
                    throw new SsgdfmCurlException($this->getCurlError()['message'], $this->getCurlError()['code']);
                }
                if ($this->getHttpError()) {
                    throw new SsgdfmHttpException($this->getHttpError()['message'], $this->getHttpError()['code']);
                }
                if($this->getApiError()) {
                    throw new SsgdfmApiException($this->getApiError()['message'],$this->getApiError()['code']);
                }
                return null;
            }
            /*
                if($response == false) {
                    if(empty($this->getCurlError())==false) {
                        $error = $this->getCurlError();
                        $getData['error'][$service] = ['status' => 1, 'code' => $error['error_no']];
                    } else if(empty($this->getHttpError())==false) {
                        $error = $this->getHttpError();
                        $getData['error'][$service] = ['status' => 1, 'code' => $error['error_no']];
                    } else if(empty($this->getApiError())==false) {
                        $error = $this->getApiError();
                        $getData['error'][$service] = ['status' => 1, 'code' => $error['error_no']];
                    } else {
                        // unexpected error
                        // 상기 3가지 경우 이외의 오류는 없음
                        $getData['error'][$service] = ['status' => 1, 'code' => 'UNDEFINED'];
                    }
                } else {
                    $getData['error'][$service] = ['status' => $response->getStatus(), 'code' => $response->getErrorCode()];
                }
            } else {
                $getData['error'][$service] = null;
                $getData = array_merge_recursive($getData, self::getPassToData($service, $response));
            }
            //Webidas::dumper($this->getError());
            //Webidas::stop();
            unset($request);
            //Webidas::dumper($getData);
            //Webidas::stop();
            */
        } catch (SsgdfmHttpException $e) {
            throw $e;
        } catch (SsgdfmCurlException $e) {
            throw $e;
        } catch (SsgdfmBuildException $e) {
            throw $e;
        } catch (SsgdfmApiException $e) {
            //Webidas::dumper($e);
            //Webidas::stop();
            throw $e;
        } catch (Exception $e) {
            throw new SsgdfmApiException($e->getCode(), $e->getMessage());
            //$this->setApiError(999, 'NOT_DEFINED_ERROR');
        }
    }


    const SSGDFM_NONE_MEMBER = 'SSGDFM_NONE_MEMBER';
    const SSGDFM_NONE_MEMBER_DUPLICATE_ID = 'SSGDFM_NONE_MEMBER_DUPLICATE_ID';
    const SSGDFM_OUT_MEMBER = 'SSGDFM_OUT_MEMBER';
    const SSGDFM_SLEEP_MEMBER = 'SSGDFM_SLEEP_MEMBER';
    const SSGDFM_NONE = 'SSGDFM_NONE'; // 추가액션 없음
    const SSGDFM_MEMBER = 'SSGDFM_MEMBER'; // 면세점 회원임

    /**
     * @param array $data
     * @param array $actionRule
     * @return mixed
     * @throws Exception
     */
    public function callProxy($data, $actionRule=[]) {
        if(!is_object($this->logger)) {
            $this->setLogger(new SsgdfmLogger());
        }
        $result = [
            'duplicateFl'=>false,
            'staffFl'=>false,
            'memberFl'=>true,
            'joinFl'=>false,
            'staffJoinFl'=>false,
            'validation'=>[]
        ];

        $actionRuleKey = ['GetDuplicate', 'GetStaff', 'GetMember', 'SetJoin', 'SetStaff'];

        if(empty($actionRule)) {
            $actionRule = ['GetDuplicate'=>true, 'GetStaff'=>false, 'GetMember'=>false, 'SetJoin'=>false, 'SetStaff'=>false];
        } else {
            foreach($actionRuleKey as $key) {
                $actionRule[$key] = gd_isset($actionRule[$key], 'n') == 'y' ? true:false;
            }
        }
        try {
            $getDuplicateRequest = null;
            if ($actionRule['GetDuplicate']) {
                $getDuplicateRequest = $this->getRequest('GetDuplicate', $data);
                $response = self::callSingle('GetDuplicate', $getDuplicateRequest);
                //Webidas::dumper($this->getError());
                //Webidas::stop();
                /**
                 * 응답 값
                 */
                //Webidas::dumper($response->getResultCode());
                if ($response->getResultCode() == 100) { // CI 및 ID 없음
                    $result['duplicateFl'] = false;
                    $result['memberFl'] = false;
                } elseif ($response->getResultCode() == 200 ) {
                    $result['duplicateFl'] = false;
                    $result['memberFl'] = true;
                } else if($response->getResultCode() == 1005) {
                    $result['duplicateFl'] = false;
                    $result['memberFl'] = true;
                } else if ($response->getResultCode() == 1001 || $response->getResultCode() == 1002) { //
                    $result['duplicateFl'] = true;
                    $result['memberFl'] = false;
                } else {
                    $result['duplicateFl'] = true;
                }
                unset($response);
            }

            //Webidas::dumper($result);

            $getStaffRequest = null;
            if ($actionRule['GetStaff']) {
                if (gd_isset($data['empNo'], false) !== false) {
                    //Webidas::dumper($data);
                    $getStaffRequest = $this->getRequest('GetStaff', $data);
                    $response = self::callSingle('GetStaff', $getStaffRequest);

                    //Webidas::dumper($response);
                    //Webidas::stop();

                    // 임직원인 경우
                    if ($response->getResultCode() == 3000) {
                        $result['staffFl'] = true;
                    }
                    unset($response);
                }
            }
            $getMemberRequest = null;
            if ($actionRule['GetMember']) {
                $getMemberRequest = $this->getRequest('GetMember', $data);
                $response = self::callSingle('GetMember', $getMemberRequest);
                $result['memberFl'] = $response->getResultCode() == 200 ? false : true;

                switch ($response->getResultCode()) {
                    case 200:
                        $result['validation']['memberCode'] = self::SSGDFM_NONE_MEMBER;
                        break;
                    case 1001:
                        $result['validation']['memberCode'] = self::SSGDFM_NONE_MEMBER_DUPLICATE_ID;
                        break;
                    case 2001:
                        $result['validation']['memberCode'] = self::SSGDFM_SLEEP_MEMBER;
                        break;
                    case 2002:
                        $result['validation']['memberCode'] = self::SSGDFM_OUT_MEMBER;
                        break;
                    default:
                        $result['validation']['memberCode'] = self::SSGDFM_NONE;
                        break;
                }

                unset($response);
            }

            if ($actionRule['SetJoin']) {
                $setJoinRequest = $this->getRequest('SetJoin', $data);
                //Webidas::dumper($setJoinRequest);

                $response = self::callSingle('SetJoin', $setJoinRequest);
                $result['joinFl'] = true;
                unset($response);
                //Webidas::stop();
            }

            if ($actionRule['SetStaff']) {
                $setStaffRequest = $this->getRequest('SetStaff', $data);
                $response = self::callSingle('SetStaff', $setStaffRequest);
                if($response->getResultCode()==200) {
                    $result['staffJoinFl'] = true;
                }
                unset($response);
            }
            return $result;
        } catch (SsgdfmHttpException $e) {
            //Webidas::dumper($e);
            //Webidas::stop();
            throw new SsgdfmIndependentException($e->getMessage(), $e->getCode());
        } catch (SsgdfmCurlException $e) {
            //Webidas::dumper($e);
            //Webidas::stop();
            throw new SsgdfmIndependentException($e->getMessage(), $e->getCode());
        } catch (SsgdfmBuildException $e) {
            //Webidas::dumper($e);
            //Webidas::stop();
            throw new SsgdfmIndependentException($e->getMessage(), $e->getCode());
        } catch (SsgdfmApiException $e) {
            //Webidas::dumper($e);
            //Webidas::stop();
            throw new SsgdfmIndependentException($e->getMessage(), $e->getCode());
        } catch (Exception $e) {
            // ssgspecial 과 독립적으로 작동해야 하므로
            // Exception 발생해도 throw 하지 않고 스킵해야 함.
            //
            //Webidas::dumper($e);
            //Webidas::stop();
            throw new SsgdfmIndependentException($e->getMessage(), $e->getCode());
            //Webidas::dumper($e);
        }
    }

    protected $jsonResult;
    public function setResponseLog($json) {
        //Webidas::dumper($json);
        //$encoded = json_decode($json);
        //$this->jsonResult = json_encode($encoded, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        //$this->jsonResult = json_encode($decodedLog, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_LINE_TERMINATORS);
        $this->jsonResult = $json;
        //Webidas::dumper($this->jsonResult);
    }

    public function getResponseLog() {
        return $this->jsonResult;
    }


    protected function parseResponse($serviceName, $response, $only_header = false) {
        $decoded = json_decode($response,true);
        //Webidas::dumper($decoded);
        //Webidas::dumper($only_header);
        //Webidas::dumper($decoded);
        $parsed = null;
        if(json_last_error()!=JSON_ERROR_NONE) {
            $this->_hasError = true;
            $this->setApiError(500, 'JSON_DECODE_ERROR');
        } else {
            //$className = str_replace('Request', 'Response',$serviceName);
            $parsed = self::_parse($decoded);
        }
        return $parsed;
    }

    /**
     * @param array $data
     * @return BaseResponseType
     */
    protected function _parse($data) {
        //Webidas::dumper($elementName);

        $current = new BaseResponseType();
        //Webidas::dumper($this->_typeMap[$elementTypeName]);

        //Webidas::dumper($current->getMetaDataElements());

        foreach ($current->getMetaDataElements() as $_typeName => $_typeValue) {
            $methodName = "set".self::convertToCamelCase($_typeName);

            //Webidas::dumper($methodName);

            if($data[$_typeName]) {
                $current->$methodName(SsgdfmSimpleType::makeValue($data[$_typeName], $_typeValue['type']));
            }
            //Webidas::dumper($current);
        }
        //Webidas::dumper($current);
        //Webidas::stop();
        return $current;
    }


    public function setPost($bool = false) {
        curl_setopt($this->client, CURLOPT_POST, $bool);
        return $this;
    }

    public function setPostFields($data) {
        curl_setopt($this->client, CURLOPT_POSTFIELDS, $data);
        return $this;
    }


    /**
     * @param mixed $headers
     * @return SsgdfmClient
     */
    public function setHttpHeader($headers = null) {
        curl_setopt($this->client, CURLOPT_HTTPHEADER, $headers);
        return $this;
    }

    /**
     * @param string $serviceName
     * @return SsgdfmClient
     */
    public function setServiceUrl($serviceName) {


        $this->serviceUrl = self::getRequestBase().self::getEndPoints($serviceName);
        //Webidas::dumper($this->serviceUrl);
        curl_setopt($this->client, CURLOPT_URL, $this->serviceUrl);
        return $this;
    }

    public function getServiceUrl($serviceName) {

        return self::getRequestBase().self::getEndPoints($serviceName);
    }

    /**
     * @param int $time
     * @return SsgdfmClient
     */
    public function setTimeout($time = 30) {
        curl_setopt($this->client, CURLOPT_TIMEOUT, $time);
        return $this;
    }

    /**
     * @param int $time
     * CURLOPT_CONNECTTIMEOUT is designed to tell the script how long to wait to make a successful connection to the server
     * before starting to buffer the output.
     * A destination's server which may be overloaded, offline or crashed would probably make this setting become useful.
     * @return SsgdfmClient
     */
    public function setConnectTimeout($time = 5) {
        curl_setopt($this->client, CURLOPT_CONNECTTIMEOUT, $time);
        return $this;
    }

    /**
     * @param boolean $bool
     * @return SsgdfmClient
     */
    public function setFollowLocation($bool = true) {
        curl_setopt($this->client, CURLOPT_FOLLOWLOCATION, $bool);
        return $this;
    }

    /**
     * @param boolean $bool
     * @return SsgdfmClient
     */
    public function setReturnTransfer($bool = true) {
        curl_setopt($this->client, CURLOPT_RETURNTRANSFER, $bool);
        return $this;
    }

    /**
     * @param int $bool
     * @return SsgdfmClient
     */
    public function setSslVerifyPeer($bool = 0) {
        curl_setopt($this->client, CURLOPT_SSL_VERIFYPEER, $bool);
        return $this;
    }

    /**
     * @param int $bool
     * @return SsgdfmClient
     */
    public function setSslVerifyHost($bool) {
        curl_setopt($this->client, CURLOPT_SSL_VERIFYHOST, $bool);
        return $this;

    }


    /**
     * @param integer $version
     * @return SsgdfmClient
     */
    public function setSslVersion($version = null) {

        if(is_null($version)) {
            curl_setopt ($this->client, CURLOPT_SSLVERSION, 6);
        } else {
            curl_setopt($this->client, CURLOPT_SSLVERSION, $version);
        }


        return $this;
    }
    /**
     * @param boolean $bool
     * @return SsgdfmClient
     */
    public function setHeader($bool = false) {
        curl_setopt($this->client, CURLOPT_HEADER, $bool);
        return $this;
    }

    /**
     * @param boolean $bool
     * @return SsgdfmClient
     */
    public function setHttpProxyTunnel($bool = true) {
        curl_setopt($this->client, CURLOPT_HTTPPROXYTUNNEL, $bool);
        return $this;
    }

    /**
     * @param mixed $caInfo
     * @return SsgdfmClient
     */
    public function setCaInfo($caInfo) {
        curl_setopt($this->client, CURLOPT_CAINFO, $caInfo);
        return $this;
    }

    /**
     * @param string $proxy
     * @return SsgdfmClient
     */
    public function setProxy($proxy) {
        curl_setopt($this->client, CURLOPT_PROXY, $proxy);
        return $this;
    }

    /**
     * @param string $port
     * @return SsgdfmClient
     */
    public function setProxyPort($port) {
        curl_setopt($this->client,CURLOPT_PROXYPORT, $port);
        return $this;
    }

    /**
     * @param boolean $bool
     * @return SsgdfmClient
     */
    public function setVerbose($bool = false) {
        curl_setopt($this->client,CURLOPT_VERBOSE, $bool);
        return $this;
    }

    /**
     * @param boolean $bool
     * @return SsgdfmClient
     */
    public function setInfoHeaderOut($bool = false) {
        curl_setopt($this->client,CURLINFO_HEADER_OUT, $bool);
        return $this;
    }

    /**
     * @return SsgdfmClient
     */
    protected function setCurlError() {
        $this->error['curl']['code'] = curl_errno($this->client);
        $this->error['curl']['message'] = curl_error($this->client);
        return $this;
    }

    public function getCurlError() {
        return $this->error['curl'];
    }

    protected function getCurlInfo($option = null) {
        $info = curl_getinfo($this->client);
        if(is_null($option)) {
            return $info;
        } else {
            return $info[$option];
        }
    }

    protected function setApiError($code, $message = null) {
        $this->error['api']['code'] = $code;
        if(!is_null($message)) {
            $this->error['api']['message'] = $message;
        } else {
            if(!in_array($code, array_keys(SsgdfmErrorType::$resultCodeType))) {
                $this->error['api']['message'] = is_null($message) ? "Undefined API Error" : $message;
            } else {
                $this->error['api']['message'] = SsgdfmErrorType::$resultCodeType[$code];
            }
        }


        return $this;
    }

    public function getApiError() {
        return $this->error['api'];
    }


    protected function setHttpError($error_no) {
        $this->error['http']['code'] = $error_no;
        $this->error['http']['message'] = "HTTP Error";
        return $this;
    }

    public function getHttpError() {
        return $this->error['http'];
    }

    /**
     * @param Exception $exception
     * @return $this
     */
    public function setBuildError($exception) {
        $this->error['build']['code'] = $exception->getCode();
        $this->error['build']['message'] = $exception->getMessage();
        return $this;
    }

    public function getBuildError() {
        return $this->error['build'];
    }

    public function getError() {
        $result = [];
        foreach($this->error as $case =>$error) {
            switch($case) {
                case 'curl':
                    $result['code']=$error['code']+10000;
                    break;
                case 'api':
                    $result['code']=$error['code']+20000;
                    break;
                case 'http':
                    $result['code']=$error['code']+30000;
                    break;
                case 'build':
                    $result['code']=$error['code']+40000;
                    break;
            }
            $result['message'] = $error['message'];
        }
        return $result;
    }

    public function hasError() {
        return $this->_hasError;
    }

    protected function parseError($response) {
        $this->_hasError = true;
        //$decoded = json_decode($response);
        $parsed = self::_parse($response);
        return $parsed;
    }



    /**
     * @param mixed $index
     * @return mixed
     */
    public function getEndPoints($index = null)
    {
        if(is_null($index)) {
            return $this->endPoints;
        } else {
            return $this->endPoints[$index];
        }


    }

    /**
     * @param mixed $index
     * @param mixed $endPoints
     */
    public function setEndPoints($endPoints, $index = null)
    {
        if(is_null($index)) {
            $this->endPoints = $endPoints;
        } else {
            $this->endPoints[$index] = $endPoints;
        }

    }

    /**
     * @return mixed
     */
    public function getRequestBase()
    {
        return $this->requestBase[$this->getEnvironment()];
    }

    public function setStdErr($output) {
        curl_setopt($this->client, CURLOPT_STDERR, $output);
        return $this;
    }

    public function getStdErr($output) {
        //rewind($output);
        rewind($output);
        $verboseLog = stream_get_contents($output);
        echo $verboseLog;
        //fclose($output);
        //return ob_get_clean();
        //return stream_get_contents($output);
    }


    public static function convertToCamelCase($value, $encoding = null) {
        if ($encoding == null){
            $encoding = mb_internal_encoding();
        }
        $stripChars = "()[]{}=?!.:,-_+\"#~/";
        $len = strlen($stripChars);
        for($i = 0; $len > $i; $i ++) {
            $value = str_replace( $stripChars [$i], " ", $value );
        }
        $value = mb_convert_case( $value, MB_CASE_TITLE, $encoding );
        $value = preg_replace( "/\s+/", "", $value );
        return $value;
    }
}