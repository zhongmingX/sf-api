<?php
namespace app\modules\member\controllers;
/**
 * Created by PhpStorm.
 * User: zhongming
 * Date: 2018/4/4 下午9:42
 */

use common\components\CommonFun;
use common\models\MembersAttracts;
use common\models\MerchantsAccount;
use \Yii;
use api\controllers\MemberBaseController;

class AttractController extends MemberBaseController{

    //招商信息
    public function actionInfo(){
        $data = [];
        $data['num'] = 0;
        $data['amount'] = 0;
        $data['audit'] = 0;
        $model = MembersAttracts::find()
            ->where('member_id=:id', [':id'=>$this->member_id])
            ->all();
        if($model){
            $tmpAmount = 0;
//            $audit = 0;
            foreach ($model as $v){
                if($v->status == MembersAttracts::STATUS_NORMAL){
                    $tmpAmount += $v->amount;
                }

                if($v->status == MembersAttracts::STATUS_AUDIT){
                    $data['audit']++;
                }
                if($v->status == MembersAttracts::STATUS_NORMAL){
                    $data['num']++;
                }
            }
            $data['amount'] = CommonFun::doNumber($tmpAmount);
        }
        return CommonFun::returnSuccess($data);

    }

    //列表
    public function actionLists(){
        $data = [];
        $query = MembersAttracts::find()
            ->with(['merchant', 'merchant.extends']);
        $data['total'] = $query->count();
        $data['page_size'] = $this->pageSize;
        $data['page_num'] = ++$this->pageNum;

        $model = $query->where('member_id=:memberId and status=:status', [':memberId'=>$this->member_id, ':status'=>MembersAttracts::STATUS_NORMAL])
            ->offset($this->offset)
            ->limit($this->pageSize)
            ->all();

        $data['lists'] = [];
        if($model){
            foreach ($model as $v){
                $data['lists'][] = [
//                    'merchant_amount' => CommonFun::doNumber($v->merchants_total_amount),
//                    'amount' => CommonFun::doNumber($v->amount),
                    'name' => $v->merchant->name,
                    'account' => $v->merchant->account,
                    'merchant_id' => $v->merchants_id,
                    'reg_date' => date("Y-m-d", $v->ctime),
                    'merchant_logo' => $v->merchant->extends->logo
                ];
            }

        }

        return CommonFun::returnSuccess($data);
    }

    //招商关系添加
    public function actionRelate(){
        if($this->isPost){
            $merchants_id = Yii::$app->request->post('merchants_id');
            $model = MerchantsAccount::findOne(['id'=>$merchants_id]);
            if(!$model){
                return CommonFun::returnFalse('current marchant is not found');
            }

            //查询关系是否存在
            $m = MembersAttracts::record($this->member_id, $merchants_id);

            if(is_string($m)){
                return CommonFun::returnFalse($m);
            }else if(is_bool($m)){
                return CommonFun::returnSuccess();
            }
        }
        return CommonFun::returnFalse('member attract fail');
    }



}