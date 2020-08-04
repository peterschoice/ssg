<?php

/**
 * This is commercial software, only users who have purchased a valid license
 * and accept to the terms of the License Agreement can install and use this
 * program.
 *
 * Do not edit or add to this file if you wish to upgrade Godomall5 to newer
 * versions in the future.
 *
 * @copyright ⓒ 2016, NHN godo: Corp.
 * @link      http://www.godo.co.kr
 */
namespace Component\Order;

use App;
use Component\Mail\MailAutoObserver;
use Component\Godo\NaverPayAPI;
use Component\Member\Member;
use Component\Naver\NaverPay;
use Component\Database\DBTableField;
use Component\Delivery\OverseasDelivery;
use Component\Deposit\Deposit;
use Component\ExchangeRate\ExchangeRate;
use Component\Mail\MailMimeAuto;
use Component\Mall\Mall;
use Component\Mall\MallDAO;
use Component\Member\Manager;
use Component\Member\Util\MemberUtil;
use Component\Mileage\Mileage;
use Component\Policy\Policy;
use Component\Sms\Code;
use Component\Sms\SmsAuto;
use Component\Sms\SmsAutoCode;
use Component\Sms\SmsAutoObserver;
use Component\Validator\Validator;
use Component\Goods\SmsStock;
use Component\Goods\KakaoAlimStock;
use Component\Goods\MailStock;
use Framework\Utility\ComponentUtils;
use LogHandler;

/**
 * 주문 class
 * @author Shin Donggyu <artherot@godo.co.kr>
 */
class Order extends \Bundle\Component\Order\Order
{
	/**
	 * 재고차감
	 * 사은품은 재고차감만 하며 복원기능은 없다
	 *
	 * @param string $orderNo     주문 번호
	 * @param array  $arrGoodsSno 일련번호 배열
	 *
	 * @internal param array $arrData 상태 정보
	 */
	public function setGoodsStockCutback($orderNo, $arrGoodsSno)
	{
		$getOrderData = $this->getOrderData($orderNo);
		if ($getOrderData['orderChannelFl'] == 'naverpay') {
			$naverpayConfig = gd_policy('naverPay.config');
			if ($naverpayConfig['linkStock'] == 'n') return ['code' => '완료', 'desc' => '네이버페이 주문 재고연동 사용안함'];
		}

		// 사음품 재고차감
		$arrInclude = [
			'giftNo',
			'giveCnt',
			'selectCnt',
			'minusStockFl',
		];
		$arrField = DBTableField::setTableField('tableOrderGift', $arrInclude, null, 'og');
		$strSQL = 'SELECT og.sno, g.giftNm, ' . implode(', ', $arrField) . ' FROM ' . DB_ORDER_GIFT . ' og LEFT JOIN ' . DB_GIFT . ' g ON g.giftNo = og.giftNo WHERE og.orderNo = ? AND og.minusStockFl = \'n\' ORDER BY og.sno ASC';
		$arrBind = [
			's',
			$orderNo,
		];
		$getData = $this->db->query_fetch($strSQL, $arrBind);
		unset($arrBind, $arrInclude, $arrField);

		if (empty($getData) === false) {
			foreach ($getData as $key => $val) {
				if (empty($val['giftNo']) === false) {
					$strWhere = 'giftNo = \'' . $val['giftNo'] . '\' AND stockFl = \'y\'';
					$affectedRow = $this->db->set_update_db(DB_GIFT, 'stockCnt = (SELECT IF((CONVERT((SELECT stockCnt), SIGNED) - ' . $val['giveCnt'] . ') < 0, 0, (stockCnt - ' . $val['giveCnt'] . ')))', $strWhere);
					unset($strWhere);

					if ($affectedRow > 0) {
						// 사은품 재고차감 로그 저장 (번역작업하지 말것)
						$this->orderLog($orderNo, '', '재고차감', '완료', $val['giftNm'] . '사은품 재고차감');

						// 재고차감 여부 체크
						$strWhere = 'sno = ' . $val['sno'];
						$this->db->set_update_db(DB_ORDER_GIFT, 'minusStockFl = \'y\'', $strWhere);
						unset($strWhere);
					}
				}
			}
		}
		unset($getData);

		// 상품 모듈 호출
		$goods = \App::load('\\Component\\Goods\\Goods');

		// 주문 상품 데이타
		$strWhere = 'sno IN (' . implode(', ', $arrGoodsSno) . ')';
		$arrInclude = [
			'orderCd',
			'goodsType',
			'goodsNo',
			'goodsNm',
			'goodsCnt',
			'optionInfo',
			'minusStockFl',
			'minusRestoreStockFl',
		];
		$arrField = DBTableField::setTableField('tableOrderGoods', $arrInclude);
		$strSQL = 'SELECT sno, ' . implode(', ', $arrField) . ' FROM ' . DB_ORDER_GOODS . ' WHERE ' . $strWhere . ' AND orderNo = ? AND minusStockFl = \'n\' ORDER BY sno ASC';
		$arrBind = [
			's',
			$orderNo,
		];
		$getData = $this->db->query_fetch($strSQL, $arrBind);
		unset($arrBind);

		if (empty($getData) === true) {
			return false;
		}

		$sendStockMail = false;
		$logCodeArr = $logDescArr = [];
		foreach ($getData as $key => $val) {
			if ($val['goodsType'] == 'addGoods') {
				// goodsNo bind data
				$this->db->bind_param_push($arrBind, 'i', $val['goodsNo']);
				$arrWhere[] = 'ag.addGoodsNo = ?';

				// 추가상품 옵션 데이타
				$this->db->strField = 'ag.stockCnt, ag.stockUseFl';
				$this->db->strWhere = implode(' AND ', $arrWhere);
				$query = $this->db->query_complete();
				$strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . DB_ADD_GOODS . ' ag ' . implode(' ', $query);
				$addGoodsData = $this->db->query_fetch($strSQL, $arrBind, false);
				$isStockCut = false;
				if (empty($addGoodsData) === false) {
					// 재고사용 조건이면서 재고가 없거나 재고수량보다 구매수량이 많은 경우 구매불가
					if ($addGoodsData['stockUseFl'] == '1') {
						if ($addGoodsData['stockCnt'] > 0 && $addGoodsData['stockCnt'] - $val['goodsCnt'] >= 0) {
							// 추가상품 재고차감
							$strWhere = 'addGoodsNo = \'' . $val['goodsNo'] . '\' AND stockUseFl = \'1\'';
							$this->db->set_update_db(DB_ADD_GOODS, 'stockCnt = (SELECT IF((CONVERT((SELECT stockCnt), SIGNED) - ' . $val['goodsCnt'] . ') < 0, 0, (stockCnt - ' . $val['goodsCnt'] . ')))', $strWhere);
							unset($strWhere);

							$logCodeArr[] = $logCode = '완료';
							$logDescArr[] = $logDesc = sprintf('기존 %s개에서 %s개 차감', number_format($addGoodsData['stockCnt']), number_format($val['goodsCnt']));
							$isStockCut = true;
						} else {
							$logCodeArr[] = $logCode = '오류';
							$logDescArr[] = $logDesc = '재고가 없어 차감 불가';
						}
					} else {
						$logCodeArr[] = $logCode = '불필요';
						$logDescArr[] = $logDesc = '무한정 재고라서 차감 없음';
					}
				} else {
					$logCodeArr[] = $logCode = '오류';
					$logDescArr[] = $logDesc = '해당 추가상품이 존재하지 않습니다.(주문이후 추가상품 변경 or 추가상품 삭제)';
				}
				unset($arrWhere, $arrBind, $tmpOption);

				// 추가상품 재고차감 로그 저장 (한번만 저장함) - 번역하지말것
				$this->orderLog($orderNo, $val['sno'], '추가상품 재고차감', $logCode, $logDesc);
			} else {
				// goodsNo bind data
				$this->db->bind_param_push($arrBind, 'i', $val['goodsNo']);
				$arrWhere[] = 'go.goodsNo = ?';

				// 옵션 where문 data
				if (empty($val['optionInfo']) === true) {
					$arrWhere[] = 'go.optionNo = ?';
					$arrWhere[] = '(go.optionValue1 = \'\' OR isnull(go.optionValue1))';
					$this->db->bind_param_push($arrBind, 'i', 1);
				} else {
					$tmpOption = json_decode(gd_htmlspecialchars_stripslashes($val['optionInfo']), true);
					foreach ($tmpOption as $oKey => $oVal) {
						$optionKey = $oKey + 1;
						$arrWhere[] = 'go.optionValue' . $optionKey . ' = ?';
						$optionNm[] = $oVal[1];
						$this->db->bind_param_push($arrBind, 's', $oVal[1]);
					}
				}
				$arrWhere[] = 'go.optionViewFl=\'y\'';

				// 상품 옵션 데이타
				$this->db->strField = 'go.optionValue1, go.optionValue2, go.optionValue3, go.optionValue4, go.optionValue5, go.stockCnt, go.sellStopFl, go.sellStopStock, go.confirmRequestFl, go.confirmRequestStock, go.optionNo, g.stockFl';
				$this->db->strWhere = implode(' AND ', $arrWhere);
				$this->db->strJoin = 'INNER JOIN ' . DB_GOODS . ' as g ON go.goodsNo = g.goodsNo';

				$query = $this->db->query_complete();
				$strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . DB_GOODS_OPTION . ' go ' . implode(' ', $query);
				$optionData = $this->db->query_fetch($strSQL, $arrBind, false);
				unset($tmpOption, $tmpValue);
				$isStockCut = false;
				if (empty($optionData) === false) {
					if ($optionData['stockFl'] == 'y') {
						// 재고가 있는 경우만 차감 처리
						if ($optionData['stockCnt'] > 0 && $optionData['stockCnt'] - $val['goodsCnt'] >= 0) {
							// 상품 재고 수정
							$this->db->set_update_db(DB_GOODS_OPTION, 'stockCnt = (SELECT IF((CONVERT((SELECT stockCnt), SIGNED) - ' . $val['goodsCnt'] . ') < 0, 0, (stockCnt - ' . $val['goodsCnt'] . ')))', str_replace('go.', '', implode(' AND ', $arrWhere)), $arrBind);

							// 재고 로그 저장
							$this->stockLog($val['goodsNo'], $orderNo, implode('/', gd_isset($optionNm, [])), $optionData['stockCnt'], ($optionData['stockCnt'] - $val['goodsCnt']), -$val['goodsCnt'], '상품 주문에 의한 재고차감');

							// 상품 전체 재고 갱신
							$goods->setGoodsStock($val['goodsNo']);

							$logCodeArr[] = $logCode = '완료';
							$logDescArr[] = $logDesc = sprintf('기존 %s개에서 %s개 차감', number_format($optionData['stockCnt']), number_format($val['goodsCnt']));
							$isStockCut = true;
							// 상품 품절 SMS
							if (($optionData['stockCnt'] - $val['goodsCnt']) <= 0) {
								$orderGoodsData[] = [
									'goodsNo' => $val['goodsNo'],
									'orderCd' => $val['orderCd'],
								];
								$this->sendOrderInfo(Code::SOLD_OUT, 'sms', $orderNo, $orderGoodsData);
								unset($orderGoodsData);
							}
							//옵션명 만들기
							for($i=1; $i<=5; $i++){
								if(!empty($optionData['optionValue'.$i])){
									$optTmp[] = $optionData['optionValue'.$i];
								}
							}
							$optionName = implode('/', $optTmp);
							/*
							현재 추가 개발진행 중이므로 수정하지 마세요! 주석 처리된 내용을 수정할 경우 기능이 정상 작동하지 않거나, 추후 기능 배포시 오류의 원인이 될 수 있습니다.
							// 판매중지 재고 옵션 SMS / 카카오톡 / 메일
							if ($optionData['sellStopFl'] == 'y' && ($optionData['stockCnt'] - $val['goodsCnt']) <= $optionData['sellStopStock']) {
								$policy = ComponentUtils::getPolicy('goods.stock_notification');
								$sms = new SmsStock($policy['goodsStock']);
								$sms->setGoods($val['goodsNo'], $val['goodsNm'], $optionData['optionNo'], $val['goodsNo'], $optionName, 'stop', $optionData['stockCnt'] - $val['goodsCnt']);
								$sms->sendSMS();
								$sms = new KakaoAlimStock($policy['goodsStock']);
								$sms->setGoods($val['goodsNo'], $val['goodsNm'], $optionData['optionNo'], $val['goodsNo'], $optionName, 'stop', $optionData['stockCnt'] - $val['goodsCnt']);
								$sms->sendKakao();
								$sendStockMail = true;
							}
							// 확인요청 재고 옵션 SMS / 카카오톡 / 메일
							if ($optionData['confirmRequestFl'] == 'y' && ($optionData['stockCnt'] - $val['goodsCnt']) <= $optionData['confirmRequestStock']) {
								$policy = ComponentUtils::getPolicy('goods.stock_notification');
								$sms = new KakaoAlimStock($policy['goodsStock']);
								$sms->setGoods($val['goodsNo'], $val['goodsNm'], $optionData['optionNo'], $val['goodsNo'], $optionName, 'request', $optionData['stockCnt'] - $val['goodsCnt']);
								$sms->sendKakao();
								$sendStockMail = true;
							}
							*/
						} else {
							$logCodeArr[] = $logCode = '오류';
							$logDescArr[] = $logDesc = '재고가 없어 차감 불가';
						}
					} else {
						$logCodeArr[] = $logCode = '불필요';
						$logDescArr[] = $logDesc = '무한정 재고라서 차감 없음';
					}
				} else {
					$logCodeArr[] = $logCode = '오류';
					$logDescArr[] = $logDesc = '해당 옵션 상품이 존재하지 않습니다.(주문이후 옵션이 변경 or 상품 삭제)';
				}
				unset($arrWhere, $arrBind);

				// 주문 로그 저장 (번역하지말것)
				$this->orderLog($orderNo, $val['sno'], '재고차감', $logCode, $logDesc);
			}

			// 공통 키값
			$arrDataKey = ['orderNo' => $orderNo];
			$goodsData['sno'][0] = $val['sno'];
			if($this->channel == 'naverpay' && $isStockCut == false){  //네이버페이고 차감안됐으면
				$goodsData['minusStockFl'][0] = 'n';
			}
			else {
				$goodsData['minusStockFl'][0] = 'y';
			}

			$goodsData['minusRestoreStockFl'][0] = 'n';

			// 주문 상품 테이블의 차감 여부 변경
			$compareField = array_keys($goodsData);
			$getGoods[0] = $getData[$key];
			$compareGoods = $this->db->get_compare_array_data($getGoods, $goodsData, false, $compareField);
			$this->db->set_compare_process(DB_ORDER_GOODS, $goodsData, $arrDataKey, $compareGoods, $compareField);
			unset($goodsData, $compareField, $getGoods, $compareGoods);
		}

		if($sendStockMail == true){
			$policy = ComponentUtils::getPolicy('goods.stock_notification');
			$sms = new MailStock($policy['goodsStock']);
			$sms->setGoods($val['goodsNo'], $val['goodsNm'], $optionData['optionNo'], $val['goodsNo'], $optionName, 'request', $optionData['stockCnt'] - $val['goodsCnt']);
			$sms->sendMail();
		}

		return ['code' => $logCodeArr, 'desc' => $logDescArr];
	}
}