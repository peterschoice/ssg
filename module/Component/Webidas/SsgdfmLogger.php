<?php
/**
 * Created by PhpStorm.
 * User: wb5398
 * Date: 2020-07-10
 * Time: 오전 11:42
 */

namespace Component\Webidas;

use Framework\Database\DBTool;
use Component\Database\DBTableField;

class SsgdfmLogger
{
    /** @var DBTool  */
    protected $db;

    protected $logSeq;

    protected $tableName;

    public function __construct()
    {
        if(!is_object($this->db)) {
            $this->db = \App::load('DB');
        }
        $this->tableName = 'es_logSsgdfmApi';
    }

    /**
     * @param mixed $transactionId
     * @param string $method
     * @return array|bool|string
     * @throws \Framework\Debug\Exception\DatabaseException
     */
    public function getLog($transactionId, $method = 'GetDuplicate') {

        $arrField = DBTableField::setTableField('tableLogSsgdfmApi');
        $strSQL = 'SELECT sno, ' . implode(', ', $arrField);
        $strSQL .= ' FROM '.$this->tableName;
        $strSQL .=' WHERE transactionId=? AND method=? order by redDt desc LIMIT 1';
        $arrBind = [
            'ss',
            $transactionId,
            $method
        ];
        $getData = $this->db->query_fetch($strSQL, $arrBind);
        //Webidas::dumper($this->db->getBindingQueryString($strSQL, $arrBind));
        if (empty($getData) === true) {
            return false;
        }
        // 내역 출력
        if (count($getData) > 0) {
            return gd_htmlspecialchars_stripslashes($getData[0]);
        } else {
            return false;
        }
    }

    /**
     * @param array $arrData
     * @param int $sno
     * @return mixed
     * @throws \Framework\Debug\Exception\DatabaseException
     */
    public function saveLog($arrData, $sno) {

        $tableField = DBTableField::tableLogSsgdfmApi();
        $getData = [];
        foreach ($tableField as $key => $val) {
            if(gd_isset($arrData[$val['val']], false)!==false) {
                if($val['val']=='sendData' || $val['val']=='receiveData') {
                    $getData[$val['val']] = $arrData[$val['val']];
                } else {
                    $getData[$val['val']] = $arrData[$val['val']];
                }

            }
        }
        $arrInclude = array_keys($getData);
        $arrBind = $this->db->get_binding($tableField, $getData, 'update', $arrInclude);
        $this->db->bind_param_push($arrBind['bind'], 'i', $sno);
        $this->db->set_update_db($this->tableName, $arrBind['param'], 'sno = ?', $arrBind['bind']);

        //Webidas::dumper($this->db->getBindingQueryString($this->db->getQueryString(), $arrBind['bind']),$arrBind['bind']);
        //Webidas::stop();

        $affectedRows = $this->db->affected_rows();

        //Webidas::dumper($affectedRows);
        //Webidas::stop();

        if($affectedRows > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * @param string $transactionId
     * @param string $method
     * @throws \Exception
     * @return int
     */
    public function beforePreLog($transactionId, $method = 'search') {

        $hasLog= self::getLog($transactionId, $method);
        if(gd_is_admin() === false ) {
            if(gd_isset($hasLog, false) !== false ) {
                return 0;
            }
        }
        $tableField = DBTableField::tableLogSsgdfmApi();
        $getData = [
            'method'=>$method,
            'transactionId'=>$transactionId,
        ];
        foreach ($tableField as $key => $val) {
            $getData[$val['val']] = gd_isset($getData[$val['val']]);
        }
        //Webidas::dumper($getData);


        $arrBind = $this->db->get_binding($tableField, $getData, 'insert');
        $this->db->set_insert_db($this->tableName, $arrBind['param'], $arrBind['bind'], 'y');
        //Webidas::dumper($this->db->getBindingQueryString($this->db->getQueryString(), $arrBind['bind']),$arrBind['bind']);
        return $this->db->insert_id();
    }
}