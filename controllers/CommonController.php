<?php


namespace api\controllers;

use common\components\Func;
use Yii;
use common\components\CommonFun;
use common\models\OpenCity;
use common\models\CommonModel;
use yii\web\Controller;
use common\models\Orders;
use common\components\enum\OrderEnum;
use common\models\Alisms;
use common\components\SendSms;
use yii\web\Response;
use common\components\CommonValidate;
use common\models\Members;
use common\models\MembersFinances;
use common\models\MerchantsApply;

class CommonController extends BaseController {
	
    public function beforeAction($action) {
        parent::beforeAction($action);
        if ($this->isGet ) {
            return true;
        }
        if($action->id != 'merchants-apply'){
            return true;
        }
        $outPut = parent::check($action);
        if ($outPut === true) {
            return true;
        }
        
    }
    /**
     * 商户申请入驻
     * @author RTS 2018-11-29 14:35:13
     */
    public function actionMerchantsApply(){
        $model = new MerchantsApply();
        $data = $this->request->post();
        if(!$model->load($this->request->post(),'')){
            return CommonFun::returnFalse('处理失败，请稍后再试。');
        }
        $res = MerchantsApply::findOne(['phone' => $model->phone]);
        if(!empty($res)){
            return CommonFun::returnFalse('手机号：'.$model->phone.'已经提交过，请耐心等待，我们会尽快安排人员与您联系');
        }
        try {
            $res = $model->save();
            if($res === false){
                throw new \Exception();
            }
            return CommonFun::returnSuccess();
        } catch (\Exception $e) {
            return CommonFun::returnFalse('处理失败，请稍后再试。'.__LINE__);
        }
    }
	/**
	 * 经纬度获取文本
	 * @param string $lat
	 * @param string $lon
	 */
	public function actionLocation($lat = '',$lon = ''){
	    if($lat == ''){
            $redis = Yii::$app->redis;
            $redis->select(2); //2表
            $openid = Yii::$app->request->headers->get('openid');
            $location = $redis->get('location:'.$openid);
            if($location){
                $location = json_decode($location, true);
                $lon = $location['longitude'];
                $lat = $location['latitude'];
            }
        }
		$res = CommonFun::getTencentLonLatInAddress($lon, $lat);
        $res['latitude'] = $lat;
        $res['longitude'] = $lon;
		return  CommonFun::returnSuccess($res);
	}
	
	/**
	 * 首页滚动订单消息
	 * @author RTS 2018年4月28日 13:10:06
	 */
	public function actionOrderMsg(){
		$where = [
			'order_status' => OrderEnum::ORDER_STATUS_DONE,
		];
		$data = Orders::getList($where,10,0,' rand() ','');
		$return = [];
		if(empty($data['data'])){
			return  CommonFun::returnSuccess($return);
		}
		foreach ($data['data'] as $item){
			if(in_array($item['type'],[OrderEnum::TYPE_ONLINEOFF_PAY,OrderEnum::TYPE_SHOP])){
				$type =  '购买商品获得省币：'.CommonFun::formatMoney($item['member_coin']);
			}else{
				$type =  '兑换商品使用省币：'.CommonFun::formatMoney($item['order_coin']);
				
			}
			$item['member_id'] = substr($item['member_id'], 4);
			$return[] = '会员：'.$item['member_id'].'***'.$type;
		}
		return  CommonFun::returnSuccess($return);
	}


    /**
     * 发送验证码
     */
    public function actionSendSms() {
        if (Yii::$app->request->isAjax || Yii::$app->request->isPost) {
            $data = ['code' => -1, 'msg' => '错误, 请重试'];
            Yii::$app->response->format = Response::FORMAT_JSON;
            $phone = Yii::$app->request->post('phone');
            $type = Yii::$app->request->post('type');

            if (!isset(Alisms::$TYPE_TO_CONST[$type])) {
                return $data['msg'] = '业务类型错误';
            }
            $obj = new SendSms(true);
            //验证请求次数
            if (false &&  $obj->checkCount($phone)) {
                $data['msg'] = '您的手机号操作过于频繁,请稍后重试';
                return $data;
            }
            //发送验证码
            $tmpType = Alisms::$TYPE_TO_CONST[$type];
            $res = $obj->verifyCode($tmpType, $phone);
            if ($res === true) {
                $data['code'] = 1;
                $data['msg'] = 'SUCCESS';
            }else{
                $data['code'] = 0;
                $data['msg'] = '发送失败，请稍候再试。';
            }

            return $data;
        };
    }

    //手机帐号密码登录
    public function actionLogin(){
        if(Yii::$app->request->isPost){
            $account = trim(Yii::$app->request->post('account', ''));
            $password = trim(Yii::$app->request->post('password', ''));

            if(!CommonValidate::isMobile($account)){
                CommonFun::returnFalse('帐号格式错误');
            }

            if(CommonFun::utf8_strlen($password) < 6){
                CommonFun::returnFalse('密码格式错误');
            }

            $password = CommonFun::md5($password, 'member-loginpass');
            $query = Members::find()
                ->where('mobile=:m and loginpass=:p', [':m'=>$account, ':p'=>$password])
                ->one();

            if($query){
                return CommonFun::returnSuccess(['member_id' => $query->id]);
            }
            return CommonFun::returnFalse('帐号密码错误');
        }
    }

    //手机帐号验证码登录
    public function actionLoginCode(){
        if(Yii::$app->request->isPost){
            $account = trim(Yii::$app->request->post('account', ''));
            $vercode = trim(Yii::$app->request->post('vercode', ''));

            if(!CommonValidate::isMobile($account)){
                return CommonFun::returnFalse('帐号格式错误');
            }

            if(CommonFun::utf8_strlen($vercode) != 6){
                return CommonFun::returnFalse('验证码格式错误');
            }

            $cv = new \common\components\SendSms();
            if (!$cv->verifyValidate(Alisms::TYPE_LOGIN, (string)$account, $vercode)) {
                return CommonFun::returnFalse('验证码错误');
            }

            //查询帐号是否存在
            $model = Members::find()
                ->where('mobile=:m', [':m'=>$account])
                ->one();

            if($model){
                return CommonFun::returnSuccess(['member_id' => $model->id]);
            }else{
                //新建帐号
                $member = new Members();
                $member->mobile = $account;
                $member->status = 1;
                $member->ctime = time();
                $member->save();

                //用户财务表
                $memberFinance = new MembersFinances();
                $memberFinance->member_id = $member->id;
                $memberFinance->save();
                return CommonFun::returnSuccess(['member_id' => $member->id]);
            }
            return CommonFun::returnFalse('登录失败');
        }
    }

    /**
     * H5页面 如果是自动登录，检验用户
     */
    public function actionH5TokenVaild(){
        if($this->isPost){
            $uid = Yii::$app->request->post('uid');
            $token = Yii::$app->request->post('token');
            $type = Yii::$app->request->post('platform');

            $vaildToken = CommonFun::md5($uid.$type.CommonFun::md5($uid).'qrcode-h5');
//            CommonFun::pp($vaildToken);
            if($vaildToken != $token){
                return CommonFun::returnFalse('token fail');
            }

            $member = Members::getInfo($uid);
            if(empty($member)){
                return CommonFun::returnFalse('account fail');
            }

            return CommonFun::returnSuccess($member);

        }
        return CommonFun::returnFalse('error!');
    }

}