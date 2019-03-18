<?php
namespace app\modules\member\controllers;


use \Yii;
use api\controllers\BaseController;
use common\components\CommonFun;
use common\models\MembersExtracts;
use common\models\MembersFinances;
use common\models\Members;
use common\models\MembersBanks;
use common\components\Notice;
use api\controllers\MemberBaseController;

class ExtractsController extends MemberBaseController{

	public function actionList(){
		$query = MembersExtracts::find();
		$query->where = [
			'member_id' => $this->member_id,
		];
		$query->orderBy('ctime desc');
		$count = $query->count('id');
		$query->limit($this->pageSize)->offset($this->offset);
		$data = $query->asArray()->all();
		
		return CommonFun::returnSuccess(['total' => $count,'list' => $data,'page_size' => $this->pageSize,'page_num' => ++$this->pageNum]);
	} 
	
    public function actionWithdraw(){
        if($this->isPost){
        	$userInfo = Members::getFinances($this->member_id);
        	$amount = floatval(Yii::$app->request->post('amount',0));

        	if($amount <= 0){
                return CommonFun::returnFalse("请输入提现金额");
            }

            if($amount < 1){
                return CommonFun::returnFalse("提现最少金额为1元");
            }
        	
        	if($userInfo['balance'] < $amount){
        		$userInfo['balance'] = CommonFun::formatMoney($userInfo['balance']);
        		return CommonFun::returnFalse("提现金额：{$amount}不能大于您的余额：{$userInfo['balance']}");
        	}
        	$today = date('Y-m-d');
        	$startTime = strtotime($today.' 00:00:00');
        	$endTime = strtotime($today.' 23:59:59');
        	$query = MembersExtracts::find()->where(['member_id' => $this->member_id]); 
        	
        	$query->andWhere(['>','ctime',$startTime]);
        	$query->andWhere(['<','ctime',$endTime]);
        	 
//        	$total = $query->count('id');
//        	if($total >= 3){
//        		return CommonFun::returnFalse("您今日提现次数超限。");
//        	}
        	
        	$password = Yii::$app->request->post('password','');
        	$info = Members::findOne(['id'=>$this->member_id]);

        	$tmp =  CommonFun::md5($password.$info->salt, 'member-txpass');
        	if($info['txpass'] != $tmp){
        		return CommonFun::returnFalse("对不起，您的提现密码错误。");
        	}
        	
        	$bank_id = intval(Yii::$app->request->post('bank_id',0));
        	$info = MembersBanks::findOne(['id' => $bank_id,'member_id' => $this->member_id]);
        	if(empty($info)){
        		return CommonFun::returnFalse("银行ID对应的数据获取失败。");
        	}
        	$userInfo->extract_freeze = CommonFun::doNumber($userInfo->extract_freeze,$amount,'+');
        	$userInfo->balance = CommonFun::doNumber($userInfo['balance'],$amount,'-');
        	$res = $userInfo->save();
       		 if($userInfo->balance >= 0 && !$res){
        		return CommonFun::returnFalse('操作失败,请稍候再试。'. json_encode($userInfo->getErrors(),JSON_UNESCAPED_UNICODE));
        	}
        	
        	$model = new MembersExtracts();
        	$model->member_id = $this->member_id;
        	$model->bank_account = $info['account'];
        	$model->bank_name = $info['bank_name'];
        	$model->account_name = $info['account_name'];
        	$model->bank_deposit = $info['bank_deposit'];
        	
        	$model->amount = $amount;
        	$model->member_oper_mobile = $this->member_id;
        	$res = $model->save();
        	if(!$res){
        		return CommonFun::returnFalse('操作失败,请稍候再试。'. json_encode($model->getErrors(),JSON_UNESCAPED_UNICODE));
        	}
        	
        	$model = new MembersExtracts();
        	$model->afterSubmit($model->id);
        	
            //给财务发消息
            Notice::submitManager('financial', '会员ID:'.$this->member_id.'发起提现申请，请及时处理！');
        	return CommonFun::returnSuccess();
        }  
    }
}