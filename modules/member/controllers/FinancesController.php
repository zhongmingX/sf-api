<?php
namespace app\modules\member\controllers;
/**
 * Created by PhpStorm.
 * User: zhongming
 * Date: 2018/4/7 下午4:15
 */
use common\components\CommonValidate;
use common\models\MembersBanks;
use common\models\MembersCurrency;
use common\models\MembersFinances;
use common\models\MembersFinancesDetail;
use common\models\MerchantsFreezes;
use common\models\MoneyAllocateds;
use common\models\Orders;
use gmars\sms\Sms;
use \Yii;
use api\controllers\BaseController;
use common\components\CommonFun;
use common\models\Members;
use common\models\Alisms;
use common\models\MembersFavorite;
use yii\db\Expression;
use api\controllers\MemberBaseController;

class FinancesController extends MemberBaseController{

    //获取用户省币列表
    public function actionCurrency(){
        $data = [];
        $query = MembersCurrency::find()
            ->where(['member_id'=>$this->member_id]);


        $total = $query->count();
        $model = $query->offset($this->offset)->limit($this->pageSize)->orderBy('ctime desc')->all();
        if($model){
            foreach ($model as $v){
                $data[] = [
                    'coin' => CommonFun::doNumber($v->coin),
                    'type' => $v->type,
                    'date' => date('Y-m-d H:i:s', $v->ctime),
                    'order_sn' => Orders::getOrderSn($v->order_sn),
                    'record' => $v->record
                ];
            }
        }
        return CommonFun::returnSuccess(['total' => $total, 'page_size'=>$this->pageSize,'page_num' => ++$this->pageNum, 'lists' => $data]);
    }

    //获取用户省币
    public function actionCurrencyNumber(){
        $model = MembersFinances::findOne(['member_id'=>$this->member_id]);
        $data = [
            'coin'=>CommonFun::doNumber($model->coin),
            'coin_freeze'=>CommonFun::doNumber($model->coin_freeze),
            'coin_expend'=>CommonFun::doNumber($model->coin_expend)
        ];
        return CommonFun::returnSuccess($data);
    }

    //获取用户余额
    public function actionBalance(){
        $model = MembersFinances::findOne(['member_id'=>$this->member_id]);
        return CommonFun::returnSuccess(['balance' => CommonFun::doNumber($model->balance),'coin' => CommonFun::doNumber($model->coin)]);
    }

    //用户资金明细
    public function actionLists(){
        $data = [];
        $category = CommonFun::getParams('category');
        $query = MembersFinancesDetail::find()->where(['member_id'=>$this->member_id, 'status'=>MoneyAllocateds::STATUS_ALLOCATED]);
        if($category){
            $tmp = explode(',', $category);
            $query->andWhere(['in', 'category', $tmp]);
        }

        $total = $query->count();
        $model = $query->offset($this->offset)->limit($this->pageSize)->orderBy('ctime desc')->all();

        if($model){
            foreach ($model as $v){
//                $record = $v->record;
//                if($v->category == MembersFinancesDetail::CATEGORY_ORDER){
//                    $record = $v->item.':'.Orders::getOrderSn($v->category_objid);
//                }
                $data[] = [
                    'category' => $v->category,
                    'type' => $v->type,
                    'amount' => CommonFun::doNumber($v->amount),
                    'balance' => CommonFun::doNumber($v->balance),
                    'item' => $v->item,
                    'order_sn' => ($v->category == MembersFinancesDetail::CATEGORY_ORDER || $v->category == MembersFinancesDetail::CATEGORY_REFUND)?Orders::getOrderSn($v->category_objid):$v->category_objid,
//                    'record' => ,
                    'date' => date('Y-m-d H:i:s', $v->ctime)
                ];
            }
        }
        return CommonFun::returnSuccess(['total' => $total, 'page_size'=>$this->pageSize,'page_num' => ++$this->pageNum, 'lists' => $data]);
    }

}