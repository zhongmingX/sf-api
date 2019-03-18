<?php
namespace app\modules\member\controllers;
/**
 * Created by PhpStorm.
 * User: zhongming
 * Date: 2018/8/28 下午2:42
 */
use common\models\Coupons;
use common\models\MembersCoupon;
use common\models\MerchantsAccount;
use \Yii;
use api\controllers\BaseController;
use common\components\CommonFun;
use api\controllers\MemberBaseController;

class CouponController extends MemberBaseController{

    /**
     * 获取用户优惠券列表
     * @param int $type 1未使用 2已使用 3过期
     */
    public function actionLists($type = 1){
        $data = [];
        if($type == 1 || $type == 3){
            //找出过期的
            $overdue = MembersCoupon::find()
                ->where('member_id=:mid and is_use = 0 and status = 1 and end_time < :date', [':mid'=>$this->member_id, ':date'=>date('Y-m-d', time())])
                ->all();
            if($overdue) {
                $ids = [];
                foreach ($overdue as $item) {
                    $ids[] = $item->id;
                }
                MembersCoupon::updateAll(['status' => 0], ['in', 'id', $ids]);
            }
        }

        $query = MembersCoupon::find()->where('member_id=:mid',[':mid'=>$this->member_id]);
        switch ($type){
            case 1:
                $query->andWhere('is_use=0 and status=1');
                break;
            case 2:
                $query->andWhere('is_use=1 and status = 1');
                $query->andWhere(['>', 'ctime', strtotime('-3 month')]);
                break;
            case 3:
            default:
                $query->andWhere('status = 0');
                $query->andWhere(['>', 'ctime', strtotime('-3 month')]);
                break;
        }

        $model = $query->with('info')->asArray()->all();
        if($model){
            foreach ($model as $k=>$item){
                if($item['info']['type'] == 1){
                    $model[$k]['amount'] = $item['amount'];
                }
                //商家
                if(isset($item['info']['merchant_id']) && $item['info']['merchant_id'] > 0){
                    $model[$k]['merchant_name'] = MerchantsAccount::getName($item['info']['merchant_id']);
                }
            }
        }
        return CommonFun::returnSuccess($model);
    }

    //领取优惠券
    public function actionReceive(){
        if($this->isPost){
            $id = Yii::$app->request->post('id', 0);
            if($id > 0){
                $res = Coupons::send($this->member_id, $id);
                if(is_bool($res) && $res == true){
                    return CommonFun::returnSuccess();
                }else{
                    return CommonFun::returnFalse($res);
                }
            }
        }
    }

    //使用券码领取优惠券
    public function actionReceiveCode(){
        $err = '';
        if($this->isPost){
            $code = trim(Yii::$app->request->post('code', ''));
            if($code){
                if(strlen($code) < 6 || strlen($code) > 20){
                    $err = '优惠券格式错误';
                }else{
                    $code = mb_strtoupper($code);
                    $coupon = Coupons::getCode($code);
                    if($coupon){
                        $res = Coupons::send($this->member_id, $coupon['id']);
                        if(is_bool($res) && $res == true){
                            return CommonFun::returnSuccess();
                        }else{
                            return CommonFun::returnFalse($res);
                        }
                    }
                }

            }
            $err = ($err)?$err:'优惠券码错误';
        }
        return CommonFun::returnFalse($err);
    }

    //获取用户在商家下优惠券
    public function actionMerchant($merchant_id){
        if(intval($merchant_id) == 0){
            return CommonFun::returnFalse('数据错误');
        }

        $sql = "select c.id as id,mc.id as mcid, count(mc.coupon_id) as mcnum from sf_coupons c,sf_members_coupon mc where c.id=mc.coupon_id and c.merchant_id={$merchant_id} and mc.member_id={$this->member_id} group by mc.coupon_id";
        $data = Yii::$app->db->createCommand($sql)->queryAll();
        return CommonFun::returnSuccess($data);
    }
}