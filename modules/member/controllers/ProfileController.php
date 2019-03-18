<?php
namespace app\modules\member\controllers;
/**
 * Created by PhpStorm.
 * User: zhongming
 * Date: 2018/4/2 上午9:42
 */
use common\components\CommonValidate;
use common\models\MembersBanks;
use common\models\WeixinFans;
use gmars\sms\Sms;
use \Yii;
use api\controllers\BaseController;
use common\components\CommonFun;
use common\models\Members;
use common\models\Alisms;
use yii\db\Expression;
use api\controllers\MemberBaseController;

class ProfileController extends MemberBaseController{

    //用户信息
    public function actionInfo(){
        $data = Members::getInfo($this->member_id, $this->api_source);
        return CommonFun::returnSuccess($data);
    }

    //用户昵称 post
    public function actionNickname(){
        if($this->isPost){
            $nickname = Yii::$app->request->post('nickname');
            if($nickname && CommonFun::utf8_strlen($nickname) >= 2){
                if($res = Members::updateNickname($this->member_id, $nickname)){
                    return CommonFun::returnSuccess($res);
                }
            }
        }
        return CommonFun::returnFalse('modified nickname fail');
    }
    
    /**
     * 发送短信
     * @author RTS 2018年4月27日 10:12:24
     */
    public function actionSendSms(){
    	if($this->isPost){
    		$mobile = Yii::$app->request->post('mobile');
    		$cv = new \common\components\SendSms(true);
    		$res = $cv->verifyCode(Alisms::TYPE_MODIFIED_PASSWORD, $mobile);
    		if($res == false){
    			return CommonFun::returnFalse('发送失败，请稍候再试。');
    		}
    		return CommonFun::returnSuccess();
    	}
    }

    //修改手机号/帐号
    public function actionAccount(){
        if($this->isPost){
            $mobile = Yii::$app->request->post('mobile');
            $vercode = Yii::$app->request->post('vercode');

            if(!CommonValidate::isMobile($mobile)){
                CommonFun::returnFalse('mobile fail');
            }

            $cv = new \common\components\SendSms();
            if (!$cv->verifyValidate(Alisms::TYPE_MODIFIED_PASSWORD, (string)$mobile, $vercode)) {
                CommonFun::returnFalse('verifycode fail');
            }

            $m = Members::findOne($this->member_id);
            if($m){
                $m->mobile = $mobile;
                if($m->save()){
                    CommonFun::returnSuccess();
                }
            }

            CommonFun::returnFalse('modified account/mobile fail.');

        }
    }

    //修改性别
    public function actionGender() {
        if ($this->isPost) {
            $sex = Yii::$app->request->post('sex');
            if(array_key_exists($sex, Members::$GENDERS)){
                $m = Members::findOne($this->member_id);
                if($m){
                    $m->sex = $sex;
                    if($m->save()){
                        CommonFun::returnSuccess();
                    }
                }
            }
        }
        CommonFun::returnFalse('modified gender fail.');
    }

    //修改地址
    public function actionAddress() {
        if ($this->isPost) {
            $address = Yii::$app->request->post('address');
            if(!empty($address) && CommonFun::utf8_strlen($address) >= 10){
                $m = Members::findOne($this->member_id);
                if($m && $m->address != $address){
                    $m->address = $address;
                    if($m->save()){
                        CommonFun::returnSuccess();
                    }
                }
            }
        }
        CommonFun::returnFalse('modified address fail.');
    }

    //密码修改
    public function actionPassword(){
        if($this->isPost){
            $types = [1,2,3]; //限定类型 1=登录密码|2=支付密码|3=提现密码
            $type = Yii::$app->request->post('type');
            $oldpassword = trim(Yii::$app->request->post('oldpassword'));
            $newpassword = trim(Yii::$app->request->post('newpassword'));
            if(!in_array($type, $types) || CommonFun::utf8_strlen($newpassword) < 6){
                CommonFun::returnFalse('密码格式不正确');
            }

            $m = Members::findOne($this->member_id);
            if($type != 1 && $m->loginpass == ''){
                CommonFun::returnFalse('请先设置登录密码');
            }

//            $oldpassword = md5($oldpassword.'shengxiaobao');
            if($m->loginpass){
                if($m->loginpass != CommonFun::md5($oldpassword.$m->salt, 'member-loginpass')){
                    CommonFun::returnFalse('登录密码错误,请重新输入');
                }
            }

//            $newpassword = md5($newpassword.'shengxiaobao');

            switch ($type){
                case 1:
                    $m->loginpass = CommonFun::md5($newpassword.$m->salt, 'member-loginpass');
                    break;
                case 2:
                    $m->paypass = CommonFun::md5($newpassword.$m->salt, 'member-paypass');
                    break;
                case 3:
                    $m->txpass = CommonFun::md5($newpassword.$m->salt, 'member-txpass');
                    break;
            }

            if($m->save()){
                CommonFun::returnSuccess();
            }

        }
        CommonFun::returnFalse('修改密码失败');
    }

    //获取平台支持银行列表
    public function actionOpenBanks() {
        $data = Yii::$app->params['bankList'];
        CommonFun::returnSuccess($data);
    }

    //提现银行列表
    public function actionBanks() {
        $model = MembersBanks::find()
            ->select(new Expression("id, bank_name, account, account_name,bank_deposit, status, from_unixtime(ctime, '%Y-%m-%d') ctime"))
            ->where('member_id=:mid and status=1', [':mid'=>$this->member_id])
            ->asArray()
            ->all();
//        CommonFun::pp($model);
//        return $model;
        return CommonFun::returnSuccess($model);
    }

    //添加提现信息
    public function actionNewBank() {
        if($this->isPost){
            $bank_name = Yii::$app->request->post('bank_name');
            $account_name = Yii::$app->request->post('account_name');
            $account_number = Yii::$app->request->post('account_number');
            
            $bank_deposit =  Yii::$app->request->post('bank_deposit','');
            if(!in_array($bank_name, Yii::$app->params['bankList'])){
                CommonFun::returnFalse('bank name fail.');
            }

            if(CommonFun::utf8_strlen($account_name) < 2){
                CommonFun::returnFalse('account name fail.');
            }

            if(CommonFun::utf8_strlen($account_number) < 10){
                CommonFun::returnFalse('account number fail.');
            }

            $model = new MembersBanks();
            $model->member_id = $this->member_id;
            $model->bank_name = $bank_name;
            $model->account = $account_number;
            $model->account_name = $account_name;
            $model->bank_deposit = $bank_deposit;
            
            $model->status = 1;
            $model->ctime = time();
            $model->save();
            if($model->save()){
                CommonFun::returnSuccess();
            }
        }
        CommonFun::returnFalse('new bank fail.');
    }

    //删除一个提现银行
    public function actionDelBank(){
        if($this->isPost){
            $id = intval(Yii::$app->request->post('id'));
            $model = MembersBanks::findOne($id);
            if($model){
                if($model->delete()){
                    CommonFun::returnSuccess();
                }
            }
        }
        CommonFun::returnFalse('delete bank fail.');
    }

    /**
     * 微信授权信息补全
     * 此次 处理weixin_fans
     * members 表验证当前帐号是否是 "微信用户" or "alipay...."
     */
    public function actionWeixinAccountPerform(){
        $data = Yii::$app->request->post('data', '');
        if($data){
            $info = WeixinFans::getInfo($this->openid);
            if($info){
                $info->nickname = $data['nickName'];
                $info->sex = $data['gender'];
                $info->headimgurl = isset($data['avatarUrl'])?urldecode(base64_decode($data['avatarUrl'])):'';
                $info->language = $data['language'];
                $info->country = $data['country'];
                $info->province = $data['province'];
                $info->city = $data['city'];
                if($info->save()){
                    $member = Members::findOne($this->member_id);
                    if($member){
                        if($member->nickname == '微信用户' || strpos($member->nickname, 'alipay') == 0){
                            $member->nickname = $data['nickName'];
                            $member->sex = $data['gender'];
                        }
                        $member->save();
                    }
                    $me = Members::getInfo($this->member_id, 'miniprogram');
                    return CommonFun::returnSuccess($me);
                }
                return CommonFun::returnFalse($info->getErrors());
            }
        }
        return CommonFun::returnFalse('无数据');
    }
}