<?php
namespace app\modules\member\controllers;
use common\components\CommonValidate;
use \Yii;
use common\components\CommonFun;
use common\models\ActivityShare;
use api\controllers\BaseController;
use common\models\CommonModel;
use api\controllers\MemberBaseController;
use common\models\ActivityCard;
use common\models\Members;
use common\models\ActivityShareRecord;
use common\models\MembersFinances;
use common\models\WeixinFans;
use common\models\MembersCurrency;

class CardController extends MemberBaseController{

    /**
     * 卡包列表
     * @return unknown
     */
    public function actionList($card_status = -1){
        $card_status = strval($card_status);
        $where = ['member_id' => $this->member_id];
        if($card_status != -1){
            if(strpos($card_status, ',') !== false){
                $card_status = explode(',', $card_status);
            }
            $where['card_status'] = $card_status;
        }
        
        $lists = ActivityCard::getList($where,$this->pageInfo,'id desc',false,['id','object_id','name','card_status','used_time','expire_time','original_price','code','object_coin']);
        return CommonFun::returnSuccess(['rows' => $lists['data'],'counts' => $lists['count']]);
    }
    
    /**
     * 卡包详情
     * @return unknown
     */
    public function actionDetails($id = 0){
        $res = ActivityCard::getOne(['member_id' => $this->member_id,'id' => $id],true,['records']);
        if($res){
            $res['end_time_seconds'] = 0;
            if($res['card_status'] == ActivityCard::STATUS_DOING){
                $res['end_time_seconds'] = strtotime($res['end_time']) - time();
            }
        }
        
        return CommonFun::returnSuccess($res);
    }
    
    /**
     * 获取助力状态
     * @param array $info
     * @return number[]|string[]
     */
    private function getHelpStatus($info = []){
        $status = [
            'status' => 0,
            'msg' => '',
        ];
        if($info['member_id'] == $this->member_id){
            $status['msg'] = '自己不能给自己助力哟';
            return $status;
        }
        if($info['card_status'] == ActivityCard::STATUS_WAITING){
            $status['msg'] = '已经不需要助力啦，好友省币已经够啦，快去通知好友领取吧';
            return $status;
        }
        if($info['card_status'] != ActivityCard::STATUS_DOING){
            $status['msg'] = '助力状态：'.ActivityCard::getStatusTxt($info['card_status']).'不允许再助力啦';
            return $status;
        }
        
        if(time() > strtotime($info['end_time'])){
            $status['msg'] = '已经不在助力时间范围内，下次早点来吧';
            return $status;
        }
        $res = ActivityShareRecord::getOne(['activity_id' => $info['object_id'],'card_id' => $info['id'],'member_id' => $this->member_id]);
        if(!empty($res)){
            $status['msg'] = '您挺热心的，您已经助力过一次啦';
            return $status;
        }
        $status['status'] = 1;
        return $status;
    }
    
    /**
     * 反查卡包详情
     * @return unknown
     */
    public function actionDetailsByObjectId($id = 0,$card_id = 0,$member_id = 0){
        if(empty($card_id)){//通过活动ID+会员ID反查助力单最后一条手动助力单
            $res = ActivityCard::getOne(['object_id' => $id,'member_id' => $member_id, 'type' => 2,'card_status' => ActivityCard::STATUS_AGO],true,['records']);
        }else{
            $res = ActivityCard::getOne(['object_id' => $id,'id' => $card_id],true,['records']);
        }
        
        if(empty($res)){
            return CommonFun::returnFalse('数据不存在');
        }
        $tmp = $this->getHelpStatus($res);
        $res['help_status'] = $tmp;
        
        $fans = WeixinFans::findOne(['member_id' => $res['member_id']]);
        $memberInfo['header_img'] = CommonFun::getArrayValue($fans,'headimgurl','');
        $memberInfo['nick_name'] = CommonFun::getArrayValue($fans,'nickname','热心省友');
        
        $tmp = Members::getFinances($res['member_id']);
        $memberInfo['coin'] = $tmp['coin'];
        $res['member_info'] = $memberInfo;
        $tmp = ActivityShare::getOne(['id' => $id]);
        $res['total_coin'] = $tmp['total_coin'];
        return CommonFun::returnSuccess($res);
    }
    
    private function getActivity($id = 0){
        $res = ActivityShare::status($id,$data);
        if($res['status'] == 0){
            return CommonFun::returnFalse($res['msg']);
        }
        return $data;
    }
    
    /**
     * 兑换
     * @return unknown
     */
    public function actionExchange(){
        $id = intval($this->request->post('id',0));
        $activity = $this->getActivity($id);
        $total_coin = $activity['total_coin'];
        $userInfo = Members::getFinances($this->member_id);
        if($userInfo['coin'] < $total_coin){
            return CommonFun::returnFalse('您的省币不足，邀请好友助力吧'); 
        }
        $now = date('Y-m-d H:i:s');
        $model = ActivityCard::getOne(['object_id' => $id,'member_id' => $this->member_id,'type' => 2]);
        $trans = Yii::$app->db->beginTransaction();
        $rd = CommonFun::randStr(6,'NUMBER');
        if($model && in_array($model['card_status'], [ActivityCard::STATUS_DOING,ActivityCard::STATUS_WAITING])){//进行中的助力 则直接结束+扣币
            try {
                $model['card_status'] = ActivityCard::STATUS_UNUSE;
                $model->remark = '助力方式';
                $model->receive_time = $now;
                $model->expire_time = $activity['end_time'];
                
               
                $model->code = ActivityCard::createNumber($id.$rd.$model->id);
                $activity->receive_counts = $activity->receive_counts + 1;
                $res = $activity->save() && $model->save();
                if($res == false){
                    throw new \Exception('变更状态失败');
                }
                $res = MembersCurrency::record($this->member_id,MembersCurrency::TYPE_REDUCE,MembersCurrency::SOURCE_SHARE_EXCHANGE,$model->code,$total_coin);
                CommonFun::log($this->member_id.'兑换：'.$id.'活动，消耗币:'.$total_coin,__FUNCTION__,'MembersFinances');
                if($res == false){
                    throw new \Exception('更新省币失败');
                }
                $trans->commit();
                return CommonFun::returnSuccess(); 
            } catch (\Exception $e) {
                $trans->rollBack();
                return CommonFun::returnFalse('兑换失败，请稍后再试：'.$e->getMessage()); 
            }
        }
        
        $res = ActivityCard::buyedCount($id,$this->member_id);
        if($res >= $activity['limit']){
            return CommonFun::returnFalse('活动限购：'.$activity['limit'].'份'); 
        }
        
        try {//直接兑换
            $model = new ActivityCard();
            $model->member_id = $this->member_id;
            $model->object_id = $id;
            $model->name = $activity['name'];
            $model->remark = '直接购买';
            $model->card_status = ActivityCard::STATUS_UNUSE;
            $model->merchant_id = $activity['merchant_id'];
            $model->receive_time = $now;
            $model->expire_time = $activity['end_time'];
            $model->original_price = $activity['original_price'];
            $model->object_coin = $activity['total_coin'];
            $activity->receive_counts = $activity->receive_counts + 1;
            $res = $activity->save() && $model->save();
            if($res == false){
                throw new \Exception('兑换失败,'.__LINE__.json_encode($activity->getErrors().json_encode($model->getErrors(),JSON_UNESCAPED_UNICODE)));
            }
            $model->code = $model::createNumber($id.$rd.$model->id);
            $model->save();
            
            $res = MembersCurrency::record($this->member_id,MembersCurrency::TYPE_REDUCE,MembersCurrency::SOURCE_SHARE_EXCHANGE,$model->code,$total_coin);
            if($res !== true){
                throw new \Exception('更新省币失败,'.$res);
            }
            CommonFun::log($this->member_id.'兑换：'.$id.'活动，消耗币:'.$total_coin,__FUNCTION__,'MembersFinances');
            $trans->commit();
            return CommonFun::returnSuccess(); 
        } catch (\Exception $e) {
            $trans->rollBack();
            return CommonFun::returnFalse('兑换失败，请稍后再试：'.$e->getMessage()); 
        }
        
        
    }
    
    /**
     * 去助力【点击助力按钮】
     * @author RTS 2018-11-9 16:18:18
     * @return json
     */
    public function actionToHelp(){
        $id = intval($this->request->post('id',0));//活动id
        $activity = $this->getActivity($id);
        
        $card_id = intval($this->request->post('card_id',0));//卡劵id
        $info = ActivityCard::getOne(['object_id' => $id,'id' => $card_id,'type' => 2]);
        if(empty($info)){
            return CommonFun::returnFalse('数据不存在');
        }
        $senderId = $info['member_id'];//发起人
        $tmp = $this->getHelpStatus($info);
        if($tmp['status'] == 0){
            return CommonFun::returnFalse($tmp['msg']);
        }
        
        $sendInfo = Members::getFinances($senderId);
        if($sendInfo['coin'] >= $activity['total_coin']){//省币够了则直接等待领取
            $info['card_status'] = ActivityCard::STATUS_UNUSE;
            $res = $info->save();
        }

        $res = ActivityShareRecord::getOne(['activity_id' => $id,'card_id' => $card_id,'member_id' => $this->member_id]);
        if(!empty($res)){
            return CommonFun::returnFalse('您挺热心的，您已经助力过一次啦');
        }
        //记录助力记录 更新劵上面累计的币 更新发起人发起人发起人【非当前登录】的币   还看助力后 是否已经够了 够了变更为待领取 推送消息叫起领取
        $senderId = $info['member_id'];//发起人
        $share_coin = $activity['share_coin'];//助力每次的币
        
        $fans = WeixinFans::findOne(['member_id' => $this->member_id]);
        $db = Yii::$app->db;
        $trans = $db->beginTransaction();
        try {
            $model = new ActivityShareRecord();
            $model->activity_id = $id;
            $model->card_id = $card_id;
            
            $model->member_id = $this->member_id;
            $model->header_img = CommonFun::getArrayValue($fans,'headimgurl','');
            $model->nick_name = CommonFun::getArrayValue($fans,'nickname','热心省友');
            $model->coin = $share_coin;
            
            $res = $model->save();
            if($res === false){
                throw new \Exception('保存助力记录失败，'.json_encode($model->getErrors(),JSON_UNESCAPED_UNICODE));
            }
            $record = MembersCurrency::record($senderId,MembersCurrency::TYPE_INCR,MembersCurrency::SOURCE_SHARE_COIN,strval($model->id),$share_coin);
            if($record !== true){
                throw new \Exception('更新省币失败:'.$record);
            }
            
            CommonFun::log($senderId.'获得：'.$this->member_id.'助力，得币:'.$share_coin,__FUNCTION__,'MembersFinances');
            $sendInfo = Members::getFinances($senderId);
            if($sendInfo['coin'] >= $activity['total_coin']){//省币够了则直接等待领取
                $info['card_status'] = ActivityCard::STATUS_WAITING;
                $res = $info->save();
                if($res == false){
                    throw new \Exception('保存卡劵失败，'.json_encode($info->getErrors(),JSON_UNESCAPED_UNICODE));
                }
                //TODO 发小程序消息
            }
           
            $trans->commit();
            $res = ActivityShareRecord::getList(['activity_id' => $id,'card_id' => $card_id],[],'id desc',false,['header_img','nick_name']);
            $res['member_coin'] = $sendInfo['coin'];
            return CommonFun::returnSuccess($res);
        } catch (\Exception $e) {
            $trans->rollBack();
            return CommonFun::returnFalse('助力失败，请稍后再试：'.$e->getMessage());
        }
    }
    
    /**
     * 生成助力单  一人一活动只能一次助力
     * @return unknown
     */
    public function actionCreateHelp(){
        $id = intval($this->request->post('id',0));//活动id
        $activity = $this->getActivity($id);
        $sendInfo = Members::getFinances($this->member_id);
        
        /*
        if($sendInfo['coin'] >= $activity['total_coin']){//省币够了则直接兑换
            return CommonFun::returnFalse('您的省币可以自己兑换啦');
        }
        */

        $info = ActivityCard::getOne(['card_status' => ActivityCard::STATUS_DOING,'member_id' => $this->member_id,'object_id' => $id,'type' => 2]);
        if(!empty($info)){
            return CommonFun::returnSuccess(['card_id' => $info->id]);
        }
        
        $model = new ActivityCard();
        $model->member_id = $this->member_id;
        $model->object_id = $id;
        $model->name = $activity['name'];
        $model->type = 2;
        $model->card_status = ActivityCard::STATUS_DOING;
        $model->merchant_id = $activity['merchant_id'];
        $model->original_price = $activity['original_price'];
        $model->object_coin = $activity['total_coin'];
        
        
        $endtime = time() + $activity['days'] * 3600 *24;
        $tmp = strtotime($activity['end_time']);
        if($endtime > $tmp){//谁小用谁的时间
            $endtime = $tmp;
        }
        $model->end_time = date('Y-m-d H:i:s',$endtime);
        $res = $model->save();
        if($res == false){
            return CommonFun::returnFalse('操作失败,'.json_encode($model->getErrors(),JSON_UNESCAPED_UNICODE));
        }
        return CommonFun::returnSuccess(['card_id' => $model->id]);   
    }
}