<?php
namespace app\modules\activity\controllers;
use common\components\CommonValidate;
use common\models\MembersAddress;
use \Yii;
use common\components\CommonFun;
use common\models\ActivityShare;
use api\controllers\BaseController;
use common\models\CommonModel;
use common\models\MerchantsAccount;
use common\models\ActivityCard;

class WxshareController extends BaseController{

    /**
     * 获取活动列表
     * @return unknown
     */
    public function actionList(){
        $lists = ActivityShare::getList(['put_status' => CommonModel::STATUS_ACTIVE],$this->pageInfo,'sort desc',false,['id','name','img','desc','end_time','total_coin','original_price']);
        return CommonFun::returnSuccess(['rows' => $lists['data'],'counts' => $lists['count']]);
    }
    
    /**
     * 获取活动详情
     * @return unknown
     */
    public function actionDetails($id = 0){ //TODO 增加活动状态
        $id = intval($id);
        $res = ActivityShare::getOne(['id' => $id],true);
        if($res){
            $res['activityStatus'] = ActivityShare::status($id);
            $member_id = Yii::$app->request->headers->get('uid');
            if(empty($member_id)){
                return CommonFun::returnFalse('Not login');
            }
            $counts = ActivityCard::buyedCount($id,$member_id);
            if($res['put_status'] == CommonModel::STATUS_DELETE || $res['activityStatus']['status'] == 0){
                $canBuyCount = 0;
            }else{
                $canBuyCount = $res['limit'] - $counts;
                $canBuyCount = $canBuyCount <= 0 ? 0 : $canBuyCount;
            }
           
            $info = ActivityCard::getOne(['object_id' => $id,'member_id' => $member_id,'type' => 2,'card_status' => [ ActivityCard::STATUS_WAITING,ActivityCard::STATUS_DOING]],true);
            $res['userActivityStatus'] = [
                'can_buy_count' => $canBuyCount,
                'id' => CommonFun::getArrayValue($info,'id',''),
                'card_status' => CommonFun::getArrayValue($info,'card_status',''),
            ];
        }
        return CommonFun::returnSuccess($res);
    }
}