<?php

namespace app\modules\merchants\controllers;

use Yii;
use api\controllers\BaseController;
use common\components\CommonFun;
use common\models\Cart;
use common\models\MerchantsProduct;
use common\models\CommonModel;
use common\models\ExchangePointProduct;
use common\models\MerchantsProductSku;
use common\models\ExchangeProductSku;
use common\models\MembersAddress;
use common\models\Orders;
use common\components\enum\OrderEnum;
use callmez\wechat\sdk\mp\Merchant;
use common\models\MerchantsAccount;
use common\models\ExchangePoint;
use common\models\ExchangeProduct;
use PetstoreIO\Order;
use yii\helpers\ArrayHelper;
use common\models\pay\WxPayService;
use common\models\Members;
use common\models\OrdersPay;
use common\models\MembersCurrency;
use common\components\FinanceSign;
use common\models\OrdersComments;
use common\models\MembersFinances;
use common\components\SendSms;
use common\models\Alisms;
use api\controllers\MemberBaseController;

class DefaultController extends MemberBaseController {
	
	public function beforeAction($action) {
		parent::beforeAction($action);
		if ($this->isGet ) {
			return true;
		}
		$outPut = parent::check($action);
		if ($outPut === true) {
			return true;
		}
		
	}
	
	/**
	 * 发送校验短信
	 * @author RTS 2018年5月3日 10:27:18
	 */
	public function actionSendSms() {
		$account = $this->request->post('account','');
		$info = MerchantsAccount::findOne(['account' => $account]);
		if(empty($info)){
			return CommonFun::returnFalse('商户帐号：'.$account.'错误，请稍候再试。');
		}
		$sms = new SendSms(true);
		$res = $sms->verifyCode(Alisms::TYPE_BIND_MERCHANTS, $account);
		if($res !== true){
			return CommonFun::returnFalse('发送失败，请稍候再试。');
		}
		return CommonFun::returnSuccess();
	}
	
	/**
	 * 绑定操作
	 * @author RTS 2018年5月3日 10:46:06
	 */
	public function actionBind() {
		$account = $this->request->post('account','');
		$info = MerchantsAccount::findOne(['account' => $account]);
		if(empty($info)){
			return CommonFun::returnFalse('商户帐号：'.$account.'错误，请稍候再试。');
		}
		$verify_code = $this->request->post('verify_code','');
		$sms = new SendSms(true);
		$res = $sms->verifyValidate(Alisms::TYPE_BIND_MERCHANTS, $account, $verify_code);
		if($res !== true){
			return CommonFun::returnFalse('验证码错误，请稍候再试。');
		}
		$user = Members::findOne($this->member_id);
		$user->merchants_id = $info['id'];
		$res = $user->save();
		if($res){
			return CommonFun::returnSuccess(['merchants_id' => $user->merchants_id]);
		}
		return CommonFun::returnFalse('操作失败，请稍候再试。');
	}

}
