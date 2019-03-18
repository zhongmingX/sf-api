<?php

namespace app\modules\notify\controllers;

use Yii;
use common\components\CommonFun;
use yii\web\Controller;
use common\models\Orders;
use common\models\CommonModel;
use common\models\OrdersShipping;
use common\components\CommonValidate;

class ExpressController extends Controller {
	
	/**
	 * 快递100推送
	 * @author RTS 2018年5月9日 13:22:14
	 */
	public function actionIndex() {
		if(!CommonValidate::isPost()){
			exit('err method');
		}
		$param = $_POST ['param'];
		$this->log( '数据：' . json_encode ( $_POST, JSON_UNESCAPED_UNICODE ));
		try {
			$params = json_decode($param,true);		
			$lastResult = CommonFun::getArrayValue($params,'lastResult',[]);
			$company = CommonFun::getArrayValue($lastResult,'com','');
			$number = CommonFun::getArrayValue($lastResult,'nu','');
			$data = CommonFun::getArrayValue($lastResult,'data','');
			$res = false;
			
			if(!empty($data) && !empty($number)){
				$res = OrdersShipping::fill($number,$data);
			}
			$this->log('成功返回：'.$res);
			exit ( '{"result":"true","returnCode":"200","message":"成功"}');
		} catch ( \Exception $e ) {
			$this->log('失败返回'.$e->getMessage());
			echo ('{"result":"false","returnCode":"500","message":"失败"}');
		}
	}
	
	private function log($msg = ''){
		CommonFun::log ( $msg, "callback", "express" );
	}
}
