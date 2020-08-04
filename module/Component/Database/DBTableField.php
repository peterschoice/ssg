<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오전 10:40
 */

namespace Component\Database;


class DBTableField extends \Bundle\Component\Database\DBTableField
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * [코드] config 필드 기본값
     *
     * @author artherot
     * @return array config 테이블 필드 정보
     */
    public static function tableLogSsgdfmApi()
    {
        // @formatter:off
        $arrField = [
            ['val' => 'memNo', 'typ' => 'i', 'def' => 0], // 회원번호
            ['val' => 'method', 'typ' => 's', 'def' => 'search'], // API 서비스메소드
            ['val' => 'transactionId', 'typ' => 's', 'def' => '0'], // API 트랜잭션ID
            ['val' => 'sendDate', 'typ' => 's', 'def' => null], // 전송일시
            ['val' => 'sendData', 'typ' => 's', 'def' => null], // 전송문
            ['val' => 'receiveData', 'typ' => 's', 'def' => null] // 수신문
        ];
        // @formatter:on

        return $arrField;
    }
}