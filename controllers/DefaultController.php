<?php

namespace api\controllers;
use common\components\CommonFun;
use common\models\MembersFinances;
use common\models\OssImg;
use yii\web\Controller;
use common\models\Express;
use common\models\OrdersShipping;
use abei2017\wx\Application;
use common\models\ActivityCard;


class DefaultController extends Controller {
    
    public function actionIndex(){
        $res = ActivityCard::createNumber('33445566');
        
        CommonFun::pp($res);
    }
    
    
//    public function actionIndex() {
//    	if(isset($_GET['number'])){
//    		$a = Express::query('中通快递',$_GET['number']);
//    		$res = -1;
//    		if($a !== false){
//    			$res = OrdersShipping::fill($_GET['number'],$a['data']);
//    		}
//    		CommonFun::pp($res);
//    		/*
//    		$a = Express::poll('中通快递','632758712214');
//    		CommonFun::pp($a);
//    		*/
//
//    	}
//
//        //CommonFun::pp(CommonFun::getTencentLonLatInAddress('104.064566','30.566529'));
//
//    	echo CommonFun::url(['/mall/default/index']);
//    	exit;
//
//        return $this->resultData('', 400);
//    }
//    public function actionTest(){
//        $id = 10492;
//        $aa = MembersFinances::useRefund($id);
//        CommonFun::pp($aa);
//    }

//    public function actionQrcode(){
//
//    }

    public function actionTest() {
        $reTxt = "感谢您关注: <a href=\"https://weixin.shenglife.cn\" data-miniprogram-appid=\"wxc79ca52b26d4b6d9\" data-miniprogram-path=\"pages/merchant/view/index?id=1003302\">人民食堂</a>";
        $reTxt .= "\n如果您需要结账请<a href=\"https://weixin.shenglife.cn\" data-miniprogram-appid=\"wxc79ca52b26d4b6d9\" data-miniprogram-path=\"pages/merchant/payment/index?id=1003302\">点击这里</a>";
        $reTxt .= "\n该商家有绑定线下兑换商品服务, 点击后可兑换<a href=\"https://weixin.shenglife.cn\" data-miniprogram-appid=\"wxc79ca52b26d4b6d9\" data-miniprogram-path=\"pages/exchange/offline/index?id=1000007\">精选商品</a>,好货直接带回家！";
        \Yii::$app->wechat->sendText('ooS7e0qDCAirKo-HW95-DynB0v_4', $reTxt);
        exit;

        $article[] = [
            'title' => '快捷结账: 立即支付得省币'.time(),
            'url' => "https://weixin.shenglife.cn/payment/checkOut/11",
            'picurl' => 'https://shenglife-shop.oss-cn-hangzhou.aliyuncs.com/2018-10-12/dcdab73d8d1ac6463a6261746c574cae.jpg'
        ];


        $res = \Yii::$app->wechat->sendNews('ooS7e0lpKC7DlszwH1iaB0F2hfLM', $article);
        CommonFun::pp($res);
    }
}
