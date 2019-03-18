<?php

namespace api\controllers;

use Yii;
use common\components\CommonFun;
use yii\web\Controller;
use common\models\Config;

class ConfigController extends Controller {
	
	/**
	 * 基础配置信息
	 * 
	 * @author RTS 2018年4月5日 11:53:02
	 */
	public function actionIndex() {
		$data = Config::getConfigs('basic'); //配置
		
		return CommonFun::returnSuccess ( [ 
				'shop_name' => $data ['shop_name'],
				'oss_host' => $data ['oss_host'],
				'product_warning' => CommonFun::getArrayValue($data,'product_warning',5),
				'exchang_warning' => CommonFun::getArrayValue($data,'exchang_warning',5),
				'token'=> 'asdaswewqe234324234ds3423',
                'signin' => Config::getConfigs('signin'), //签到配置
                'luckygrid' => Config::getConfigs('luckygrid') //转盘配置
		] );
	}
}
