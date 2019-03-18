<?php

namespace app\modules\exchange\controllers;

use api\controllers\BaseController;
use common\components\enum\OrderEnum;
use common\extend\OSS\Common;
use common\models\Category;
use common\components\CommonFun;
use common\components\CommonGeoLocal;
use common\models\ExchangePoint;
use common\models\CommonModel;
use common\models\ExchangePointProduct;
use common\models\ExchangePorintSetting;
use common\models\ExchangeProduct;
use common\models\MembersFavorite;
use common\models\OpenCity;
use common\models\Orders;

/**
 * Default controller for the `local` module
 */
class PointController extends BaseController
{
	/**
	 * 获取附近兑换点
	 * @param number $distance
	 */
    public function actionIndex(){
        $lat = $this->lat;
        $lng = $this->lon;
        if(!$lat || !$lng){
            return CommonFun::returnFalse('当前未定位');
        }

        $distance = CommonFun::getParams('distance', 0); //距离

        $sql = "SELECT
            a.id, a.name,a.address, a.lat, a.lon, a.img,
            ROUND(6378.138*2*ASIN(SQRT(POW(SIN((".$lat."*PI()/180-lat*PI()/180)/2),2)+COS(".$lat."*PI()/180)*COS(lat*PI()/180)*POW(SIN((".$lng."*PI()/180-lon*PI()/180)/2),2)))*1000)
            AS
             distance 
            FROM sf_exchange_point a
            where a.lon <> '' and a.lat <> '' and a.is_online=0 and a.status=1";

        if($distance > 0){
            $sql .= " having distance <= ".($distance * 1000);
        }

        $sql .= " ORDER BY distance asc";
        $sql .= " limit ".$this->offset. ','. $this->pageSize;

        $db = \Yii::$app->db;
        $query = $db->createCommand($sql);
        $data = $query->queryAll();
        if($data){
            foreach($data as $k=>$v){
                $data[$k]['product_counts'] = ExchangePointProduct::find()->where(['exchange_point_id'=>$v['id'],'status' => CommonModel::STATUS_ACTIVE])->count('id');
                $data[$k]['focus'] = MembersFavorite::getCounts($v['id'],MembersFavorite::TYPE_EXCHANGE);
                $data[$k]['order_nums'] = Orders::getCount($v['id'], OrderEnum::TYPE_EXCHANGE_OFF);
                $data[$k]['distance'] = sprintf("%0.2f", $v['distance'] / 1000);
            }
        }
        return CommonFun::returnSuccess([
            'lists' => $data,
            'total' => count($data)
        ]);
    }
    
    /** 
     * 兑换点详情
     * @author RTS 2018年4月5日 14:19:41
     */
    public function actionBasic($id = 0) {
    	$model = ExchangePoint::find()->where(['id' => $id,'status' => CommonModel::STATUS_ACTIVE])->asArray()->one();
    	if(empty($model)){
    		return CommonFun::returnFalse('对应的信息不存在');
    	}
    	$prodcut_counts = ExchangePointProduct::find()->where(['exchange_point_id'=>$id,'status' => CommonModel::STATUS_ACTIVE])->count('id');
    	$focus = MembersFavorite::getCounts($id,MembersFavorite::TYPE_EXCHANGE);
    	$order_num = Orders::getCount($id, OrderEnum::TYPE_EXCHANGE_OFF);
    	$setting = ExchangePorintSetting::findOne(['id'=>$id]);
    	if($setting){
            $setting = $setting->toArray();
        }
    	return CommonFun::returnSuccess(['focus' => $focus,'prodcut_counts' => $prodcut_counts, 'order_num'=>$order_num,'info' => $model, 'setting'=>$setting]);
    }

    /**
     * 查询用户在当前兑换点订单情况， 处理限购条件  //TODO 此方法已移至 member 下 CommonController.php 
     * @param $id  兑换点ID
     */
    public function actionMemberLimitOrders($id){
        $uid = \Yii::$app->request->headers->get('uid');
        $data = ['coin_order'=>0, 'amount_order'=>0];
        $setting = ExchangePorintSetting::findOne(['id'=>$id]);
        if($setting){
            $limit_cycle = ($setting['limit_cycle'] != 0)?($setting['limit_cycle']*(60*60*24)):0;
            if($setting->limit_number != 0){
                $order = Orders::find();
                $order->where('member_id=:mid and type=:type and order_obj_id=:oid', [':mid' => $uid, ':type'=>OrderEnum::TYPE_EXCHANGE_OFF, ':oid'=>$id]);
                $order->andWhere(['not in', 'order_status', [8,9]]);
//                $order->groupBy('product_id');
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
}
