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

    /**
     * [LOG] tableLogSfApi 필드 기본값
     *
     * @date 2020-08-05 10:11:50 junlae.kim@webidas.com
     * @return array tableLogSfApi 테이블 필드 정보
     */
    public static function tableLogSfApi()
    {
        // @formatter:off
        $arrField = [
            ['val' => 'transactionFl', 'typ' => 's', 'def' => 'order'], // 트랜잭션 유형
            ['val' => 'transactionNo', 'typ' => 's', 'def' => null], // 트랜잭션 참조번호
            ['val' => 'serviceNm', 'typ' => 's', 'def' => 'apiOrderRequest'], // 요청 메소드
            ['val' => 'sendDate', 'typ' => 's', 'def' => null], // 전송일시
            ['val' => 'sendData', 'typ' => 's', 'def' => null], // 전송 xml
            ['val' => 'receiveData', 'typ' => 's', 'def' => null], // 수신 xml
        ];
        // @formatter:on

        return $arrField;
    }
}