<?php

namespace app\modules\notify\controllers;

use Yii;
use common\components\CommonFun;
use yii\web\Controller;
use common\models\pay\WxPayService;
use common\models\OrdersPay;
use common\models\MembersCurrency;
use common\models\Orders;
use common\models\Members;
use common\models\MembersFinances;
use common\components\enum\OrderEnum;
use common\models\MembersFinancesDetail;
use common\models\MoneyAllocateds;

class WxController extends Controller {
	
	/**
	 * 微信支付回调
	 * 
	 * @author RTS 2018年4月9日 14:12:10
	 */
	public function actionPay() {
		$xml = isset ( $GLOBALS ['HTTP_RAW_POST_DATA'] ) ? $GLOBALS ['HTTP_RAW_POST_DATA'] : "";
		$notify_array = CommonFun::FromXmlToArray ( $xml );

		$wxpayserver = new WxPayService ();
		$notify_bool = $wxpayserver->notifyPubVerification ( $notify_array, 2, Yii::$app->wechat->partnerKey );
		CommonFun::log ( "收到微信回调,校验结果：".$notify_bool.",数据：" . json_encode ( $notify_array, JSON_UNESCAPED_UNICODE ), "wxpay", "notify" );
		if ($notify_bool) { // 校验成功
			$out_trade_no = $notify_array ["out_trade_no"];
			$transaction_id = $notify_array ["transaction_id"];
			$total_fee = $notify_array ["total_fee"];
			
			$data = OrdersPay::find ()->where ( [ 
					'trade_number' => $out_trade_no,
					'status' => 1,
					'pay_status' => 0 
			] )->all();
			$res = -1;
			$userInfo = [];
			if (! empty ( $data )) {
				foreach ( $data as $item ) {
					$res = MembersFinances::usePayment($item ['member_id'],$item['pay_balance_amount'],$item['pay_coin'],$item ['order_id'],$item['pay_amount']);
					if($res === true){
						$totalPay = CommonFun::doNumber($item['pay_balance_amount'],$item['pay_amount'],'+');
						
						$payInfo = [
							'pay_coin' => $item['pay_coin'],
							'pay_amount' => $totalPay,

							'pay_balance' => $item['pay_balance_amount'],
							'pay_third_amount' => $item['pay_amount'],
						];
					
						$item->third_number = $transaction_id;
						$item->pay_status = OrderEnum::PAY_STATUS_PAYED;
						$where ['id'] = $item ['order_id'];
						$res = Orders::operation ( $where, 6, '用户', $payInfo );
					}
					$item->remark = $res;
					$item->save(false);
				}
			}
		}
		
		CommonFun::log ( "处理结果:" . json_encode($res,JSON_UNESCAPED_UNICODE) . ',数据：' . json_encode ( $notify_array, JSON_UNESCAPED_UNICODE ), "wxpay", "notify" );
		echo "SUCCESS";
		exit ();
	}
}
