<?php
/**
 * Created by PhpStorm.
 * User: FDF00396
 * Date: 2020-08-05
 * Time: 오전 9:42
 */

namespace Component\Sf;

use Component\Webidas\Webidas;
use Framework\Database\DBTool;
use Component\Database\DBTableField;
use Request;
use Exception;
use App;

class SfApiLogger
{
    /** @var DBTool  */
    protected $db;

    protected $logSeq;

    protected $serviceName;

    public function __construct()
    {
        if(is_object($this->db)) {
            $this->db = App::load('DB');
        }
    }

    /**
     * @return mixed
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @param mixed $serviceName
     */
    public function setServiceName($serviceName)
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @param $transactionFl
     * @param $transactionNo
     * @param string $sendStatus
     * @return array|bool|string
     * @throws \Framework\Debug\Exception\DatabaseException
     */
    public function getLog($transactionFl, $transactionNo) {

        $arrField = DBTableField::setTableField('tableLogSfApi');
        $strSQL = 'SELECT sno, ' . implode(', ', $arrField) . ' FROM tableLogSfApi WHERE transactionFl = ? and transactionNo = ? order by sno desc';
        $arrBind = [
            'ss',
            $transactionFl,
            $transactionNo
        ];
        $getData = $this->db->query_fetch($strSQL, $arrBind);

        if (is_null($getData) === true) {
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

        $tableField = DBTableField::tableLogSfApi();
        $getData = [];
        foreach ($tableField as $key => $val) {
            if(gd_isset($arrData[$val['val']], false)!==false) {
                if($val['val']=='sendData' || $val['val']=='receiveData') {
                    $getData[$val['val']] = urlencode($arrData[$val['val']]);
                } else {
                    $getData[$val['val']] = $arrData[$val['val']];
                }

            }
        }
        $arrInclude = array_keys($getData);
        $arrBind = $this->db->get_binding($tableField, $getData, 'update', $arrInclude);
        $this->db->bind_param_push($arrBind['bind'], 'i', $sno);
        $this->db->set_updatedb('tableLogSfApi', $arrBind['param'], 'sno = ?', $arrBind['bind']);

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
     * @param string $transactionFl
     * @param mixed $transactionNo
     * @throws \Exception
     * @return int
     */
    public function beforePreLog($transactionFl, $transactionNo) {

        //$hasLog= self::getLog($orderNo, $sendStatus);
        /*if(gd_isset($hasLog, false) !== false) {
            return 0;
        } else {*/
        $tableField = DBTableField::tableLogSfApi();
        $getData = [
            'transactionFl'=>$transactionFl,
            'transactionNo'=>$transactionNo
        ];
        foreach ($tableField as $key => $val) {
            $getData[$val['val']] = gd_isset($getData[$val['val']]);
        }
        $getData['serviceNm'] = $this->getServiceName();
        $arrBind = $this->db->get_binding($tableField, $getData, 'insert');
        $this->db->set_insertdb('tableLogSfApi', $arrBind['param'], $arrBind['bind'], 'y');
        //Webidas::dumper($this->db->getQueryString(), $arrBind);
        return $this->db->insert_id();
        /*}*/
    }
}