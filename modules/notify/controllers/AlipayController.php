<?php

namespace app\modules\notify\controllers;

use common\models\pay\AliMiniService;
use Yii;
use common\components\CommonFun;
use yii\web\Controller;
use common\models\OrdersPay;
use common\models\Orders;
use common\models\MembersFinances;
use common\components\enum\OrderEnum;

class AlipayController extends Controller {
    //支付宝回调
    public function actionPay(){
        $notify_array = $_POST;
//        $notify_array = '{"gmt_create":"2018-09-29 11:36:09","charset":"UTF-8","seller_email":"shenglife198@163.com","subject":"支付省生活商城订单","sign":"IUD9emlSnzrcO6uyqdZmwf6773bLomUfnnDi49UMT4B5gbxhKbl3iwzOjaViApmAQ650hkSbM+C+iRWDWeM5pHg+SxKsRk9Cs5OnkdeIMjt\/p8HLdy5LGyfAt9BO0+IvsWkLXuMxRXVKf8d2at8HQ2GXKR88NMwBAQ37AJpaZxijLvBPjpgdvzdnBy\/NtXBFliHfAnaS4+iFGmGJRMywmU4gt4TjQk+pFqpVeesxVn9Du1WaltIo2Ehud2ISe7AeHFnlrVaSJoUeRoJFOCkmd7YH8R1RBkgrdNz\/k3yM7tEEUtZ+5OpGZK6LYl\/+YdhIt0WK9BXMhuds16tEGXVexw==","buyer_id":"2088202279261083","invoice_amount":"0.01","notify_id":"2018092900222113616061080508658231","fund_bill_list":"[{\"amount\":\"0.01\",\"fundChannel\":\"ALIPAYACCOUNT\"}]","notify_type":"trade_status_sync","trade_status":"TRADE_SUCCESS","receipt_amount":"0.01","buyer_pay_amount":"0.01","app_id":"2018090661290548","sign_type":"RSA2","seller_id":"2088131566758334","gmt_payment":"2018-09-29 11:36:15","notify_time":"2018-09-29 11:36:16","version":"1.0","out_trade_no":"1809T64DSVA7IQX3JSI13CDH545YOVHF","total_amount":"0.01","trade_no":"2018092922001461080581262710","auth_app_id":"2018090661290548","buyer_logon_id":"223***@qq.com","point_amount":"0.00"}';
//        $notify_array = json_decode($notify_array, true);
        CommonFun::log ( "收到支付宝回调, 数据：" . json_encode ( $notify_array, JSON_UNESCAPED_UNICODE ), "alipay", "notify" );

//        $pay_no = $notify_array['out_trade_no'];
//        $pays = AliMiniService::query($pay_no);
        if(($notify_array['trade_status'] == 'TRADE_FINISHED' OR $notify_array['trade_status']  == 'TRADE_SUCCESS')){
            $trade_no = $notify_array ["trade_no"];
            $data = OrdersPay::find ()->where ( [
                'trade_number' => $notify_array['out_trade_no'],
                'status' => 1,
                'pay_status' => 0
            ] )->all();
            $res = -1;
            if (! empty ( $data )) {
                foreach ( $data as $item ) {
                    $res = MembersFinances::usePayment($item ['member_id'], $item['pay_balance_amount'], $item['pay_coin'], $item ['order_id'], $item['pay_amount']);
                    if($res === true){
                        $totalPay = CommonFun::doNumber($item['pay_balance_amount'], $item['pay_amount'],'+');

                        $payInfo = [
                            'pay_coin' => $item['pay_coin'],
                            'pay_amount' => $totalPay,

                            'pay_balance' => $item['pay_balance_amount'],
                            'pay_third_amount' => $item['pay_amount'],
                        ];

                        $item->third_number = $trade_no;
                        $item->pay_status = OrderEnum::PAY_STATUS_PAYED;
                        $where ['id'] = $item ['order_id'];
                        $res = Orders::operation ( $where, 6, '用户', $payInfo );
                    }
                    $item->remark = $res;
                    $item->save(false);
                }
            }
            CommonFun::log ( "处理结果:" . json_encode($res,JSON_UNESCAPED_UNICODE), "alipay", "notify" );
        }
        echo 'success';
        exit;
    }
}
