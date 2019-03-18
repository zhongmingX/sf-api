<?php
namespace app\modules\member\controllers;
use \Yii;
use common\components\CommonFun;

use api\controllers\MemberBaseController;
use common\models\ExchangePorintSetting;
use common\models\Orders;
use common\components\CommonValidate;
use common\components\SendSms;
use common\models\Members;
use common\models\CommonModel;
use common\models\WeixinFans;
use common\models\AlipayFans;
use common\models\MembersFinances;


/**********************处理其他基类需要用户登录的actions RTS 2018年10月11日16:59:51*****************************/
class CommonController extends MemberBaseController{
    
    
    /**
     * 查询用户在当前兑换点订单情况， 处理限购条件
     * @param $id  兑换点ID
     */
    public function actionMemberLimitOrders($id){
        $data = ['coin_order'=>0, 'amount_order'=>0];
        $setting = ExchangePorintSetting::findOne(['id'=>$id]);
        if($setting){
            $limit_cycle = ($setting['limit_cycle'] != 0)?($setting['limit_cycle']*(60*60*24)):0;
            if($setting->limit_number != 0){
                $order =  Orders::find();
                $order->where('member_id=:mid and order_obj_id=:oid', [':mid' => $this->member_id, ':oid'=>$id]);
                $order->andWhere(['not in', 'order_status', [8,9]]);
                $order->groupBy('product_id');
                if($limit_cycle != 0){
                    $order->andWhere(['>=', 'ctime', time() - $limit_cycle]);
                }
                
                $res = $order->all();
                if($res){
                    foreach($res as $item){
                        if($item->product_amount == 0){
                            $data['coin_order'] += 1;
                        }else{
                            $data['amount_order'] += 1;
                        }
                    }
                }
            }
            return CommonFun::returnSuccess($data);
        }
        
        return CommonFun::returnFalse('系统错误! 请联系客服');
    }
    
    public function actionBindMobile(){
        if($this->isPost){
            $mobile = $this->request->post('mobile','');
            $code = $this->request->post('code','');
            $type = $this->request->post('type',10);
            
            if(empty($mobile) || empty($code)){
                return CommonFun::returnFalse('缺少必要参数:'.__LINE__);
            }
            
            if(!CommonValidate::isMobile($mobile)){
                return CommonFun::returnFalse('手机号错误:'.__LINE__);
            }
            
            $obj = new SendSms(true);
            $res = $obj->verifyValidate($type, $mobile, $code);
            if(!$res){
                return CommonFun::returnFalse('验证码错误');
            }
            
            $info = Members::findOne($this->member_id);
            $info->mobile = $mobile;
            $res = $info->save();
            if($res !== true){
                return CommonFun::returnFalse('操作失败');
            }
            $info = Members::find()->where(['mobile' => $mobile,'status' => CommonModel::STATUS_ACTIVE])->all();
            $counts = count($info);
            
            if($counts == 1){
                return CommonFun::returnSuccess();
            }
            
            if($counts != 2){
                CommonFun::log([$info,$this->member_id,'mobile' => $mobile],'',__FUNCTION__);
                return CommonFun::returnFalse('账户异常，请联系我们');
            }
            
            //eq. member table source_type|openplatform_type 2018年10月26日16:20:37
            $ids = '';
            foreach ($info as $item){
                if($item->id == $this->member_id && $this->api_source == 'alipay'){
                    $data['alipay']['member_id'] = $item->id;
                    $ids .= $item->id;
                }else{
                    $data['weixin']['member_id'] = $item->id;
                    $ids .= $item->id;
                }
            }
            
            $data['token'] = CommonFun::md5($ids.'shenglife@');
            return CommonFun::returnSuccess($data);
        }
    }
    
    public function actionAccountMerge(){
        if($this->isPost){
            $from_source = $this->request->post('from_source', 'weixin');
            $token = $this->request->post('token','');
            $from = $this->request->post('from', '');
            $to = $this->request->post('to', '');
            if(empty($from) || empty($to)){
                return CommonFun::returnFalse('请选择保留账号');
            }
            
            $tmpToken = CommonFun::md5($from.$to.'shenglife@');
            if($token != $tmpToken){
                $tmpToken = CommonFun::md5($to.$from.'shenglife@');
                if($token != $tmpToken){
                    return CommonFun::returnFalse('数据错误！');
                }
            }
            if($from_source == 'weixin'){
//                $model = WeixinFans::findOne(['member_id' => $from]);
                try{
                    WeixinFans::updateAll(['member_id' => $to], 'member_id=:id', [':id'=>$from]);
                }catch (\Exception $e){
                    CommonFun::log($e, __FUNCTION__, 'merge');
                    return CommonFun::returnFalse('操作失败，请稍后再试。');
                }
            } else {
                $model = AlipayFans::findOne(['member_id' => $from]);
                if(!$model){
                    return CommonFun::returnFalse('获取粉丝信息失败。');
                }
                //TODO 修改被合并的用户id到新的id上面
                $model->member_id = $to;
                $res = $model->save();
                if($res !== true){
                    return CommonFun::returnFalse('操作失败，请稍后再试。');
                }
            }

            $fromFinancesModel = MembersFinances::findOne(['member_id' => $from]);
            $toFinancesModel = MembersFinances::findOne(['member_id' => $to]);
            CommonFun::log([$fromFinancesModel,$toFinancesModel],__FUNCTION__,'merge');
            if($fromFinancesModel){
                if($fromFinancesModel->balance > 0){//转钱
                    $toFinancesModel->balance = CommonFun::doNumber($toFinancesModel->balance,$fromFinancesModel->balance,'+');
                    $fromFinancesModel->balance = 0;
                }
                if($fromFinancesModel->coin > 0){//转币
                    $toFinancesModel->coin = CommonFun::doNumber($toFinancesModel->coin,$fromFinancesModel->coin,'+');
                    $fromFinancesModel->coin = 0;
                }
                $res = $toFinancesModel->save() && $fromFinancesModel->save();
                if($res !== true){
                    return CommonFun::returnFalse('操作失败，请稍后再试。');
                }
                CommonFun::log([$fromFinancesModel,$toFinancesModel],__FUNCTION__,'merge');
            }

            $model = Members::findOne($from);
            $model->status = Members::STATUS_DISABLE;
            $model->save();
            return CommonFun::returnSuccess();
        }
        
    }
    
    
}