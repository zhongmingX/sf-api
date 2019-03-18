<?php

namespace app\modules\local\controllers;

use api\controllers\BaseController;
use common\components\Func;
use common\extend\OSS\Common;
use common\models\Category;
use common\components\CommonGeoLocal;
use common\models\CityCode;
use common\components\CommonFun;
use common\models\Coupons;
use common\models\MerchantsAccount;
use common\models\MerchantsAccountExtends;
use common\models\MerchantsAccountSetting;
use common\models\MerchantsExtracts;
use common\models\MerchantsLocalFeatures;
use common\models\MerchantsLocalProduct;
use common\models\MerchantsLocalTag;
use common\models\OpenCity;
use common\models\ExchangePoint;
use common\models\Orders;

/**
 * Default controller for the `local` module
 */
class MerchantsController extends BaseController
{
    //取出附近商家
    private function getGeolocal($num = 20){
        $key = CommonGeoLocal::getKey('local:',$this->region);
        $res = CommonGeoLocal::getLocal($key, $this->lon, $this->lat, $num);
        return $res;
    }

    //附近
    public function actionNear(){
        //看下是不是当前城市
        $citys = OpenCity::findOne($this->city_id);
        if($citys && $citys->name != $this->region){
            return CommonFun::returnSuccess();
        }

        $res = $this->getGeolocal();
        if(!$res) {
            return CommonFun::returnSuccess();
        }

        $data = [];
        foreach ($res as $v){
            $model = MerchantsAccount::getLocalData($v['merchants_id'], $v['distance']);
            if($model){
                $data[] = $model;
            }
        }

        return CommonFun::returnSuccess($data);
    }

    /**
     * 根据城市来获取所有商家
     * @throws \yii\db\Exception
     */
    public function actionCityLists(){
        $city = CommonFun::getParams('city', '');
        if($city == 'undefined'){
            $city = '';
        }

        $sql = "SELECT
            a.id, a.name, a.account,
            ae.longitude, ae.latitude, ae.logo, ae.address, ae.slogan,
            mas.local_category 
            FROM sf_merchants_account_extends ae, sf_merchants_account a, sf_merchants_account_setting mas
            where longitude <> '' and latitude <> '' and ae.id=a.id and a.id=mas.id and a.status=2 and a.is_local=1";
        if($city){
            $sql .= " and find_in_set('".$city."',ae.address)";
        }
        $db = \Yii::$app->db;
        $query = $db->createCommand($sql);
        $data = $query->queryAll();
        return CommonFun::returnSuccess($data);
    }

    /**
     * @param $city 城市
     * @param $order_by 排序名
     * @param $sort 排序方式
     * @param $name 商家名称
     * @param $category_id 分类ID
     *
     * @throws \yii\db\Exception
     */
    public function actionList(){
        $lat = $this->lat;
        $lng = $this->lon;
        if(!$lat || !$lng){
            return CommonFun::returnFalse('未定位');
        }
        $city = CommonFun::getParams('city', '');
        if($city == 'undefined'){
            $city = '';
        }
        $order_by = CommonFun::getParams('order_by', '');
        $sort = CommonFun::getParams('sort', 0);
        $name = CommonFun::getParams('name', '');
        $category_id = CommonFun::getParams('category_id', 0);

        $distance = CommonFun::getParams('distance', 0); //距离

        if($sort == 0){
            $sort = 'asc';
        }else{
            $sort = 'desc';
        }

        $sql = "SELECT
            a.id, a.name, a.account,
            ae.longitude, ae.latitude, ae.logo, ae.address, ae.slogan,
            ae.open_date, ae.peple_average,
            mas.local_category,
            ROUND(6378.138*2*ASIN(SQRT(POW(SIN((".$lat."*PI()/180-latitude*PI()/180)/2),2)+COS(".$lat."*PI()/180)*COS(latitude*PI()/180)*POW(SIN((".$lng."*PI()/180-longitude*PI()/180)/2),2)))*1000)
            AS
             distance
            FROM sf_merchants_account_extends ae, sf_merchants_account a, sf_merchants_account_setting mas
            where longitude <> '' and latitude <> '' and ae.id=a.id and a.id=mas.id and a.status=2 and a.is_local=1";
        if($city){
            $sql .= " and find_in_set('".$city."',ae.address)";
        }
        if($name){
            $sql .= " and a.name like '%".$name."%'";
        }

        $category_id = intval($category_id);
        if($category_id > 0){
            $sql .= " and find_in_set(".$category_id.", mas.local_category)";
        }

        if($distance > 0){
            $sql .= " having distance <= ".($distance * 1000);
        }

        if($order_by){
            $sql .= " ORDER BY ".$order_by.' '.$sort;
        }else{
            $sql .= " ORDER BY distance asc";
        }

        //这里直接设置50个， 前端打包的时候放开
        $sql .= " limit ".$this->offset. ','. $this->pageSize;

//        CommonFun::pp($sql);

        $db = \Yii::$app->db;
        $query = $db->createCommand($sql);
        $data = $query->queryAll();
        if(!$data){
            return CommonFun::returnSuccess();
        }

        foreach ($data as $k=>$v){
            $flags = [];
            //查找有没有兑换点
            $point = ExchangePoint::findOne(['merchants_id'=>$v['id'], 'status'=>1]);
            if($point){
                $pointArr['key'] = 1;
                $pointArr['item'] = '兑换点';
                $flags = [$pointArr];
            }

            //查商家有没有优惠券
            $coupons = Coupons::findOne(['merchant_id'=>$v['id'], 'type'=>1]);
            if($coupons){
                $flags[] = ['key'=>$coupons->id, 'item'=>$coupons->name];
            }

            $data[$k]['tags'] = MerchantsLocalTag::getTags($v['id']);
            $data[$k]['flags'] = $flags;
            $data[$k]['distance'] = sprintf("%0.2f", $v['distance'] / 1000);
//            $data[$k]['order_num'] = Orders::getCount($v['id'], 3);
        }
        return CommonFun::returnSuccess(['page_size'=>$this->pageSize,'page_num' => ++$this->pageNum, 'lists' => $data]);
//        CommonFun::pp(MerchantsLocalTag::getTags(1003313));
    }

    //分类商家、搜索
    public function actionCategory($category_id, $search='', $sort='near'){
        $ms = $this->getGeolocal();
        if(!$ms) {
            return CommonFun::returnSuccess();
        }

        $mids = MerchantsAccountSetting::getCategoryAccount($category_id, $search);

        $filterid = []; //合并附近
        foreach ($mids as $k=>$v){
            foreach ($ms as $kk=>$vv){
                if($v['merchants_id'] == $vv['merchants_id']){
                    $filterid[] = $ms[$kk];
                }
            }
        }

        $data = [];
        foreach ($filterid as $v){
            $model = MerchantsAccount::getLocalData($v['merchants_id'], $v['distance']);
            if($model){
                $data[] = $model;
            }
        }

        return CommonFun::returnSuccess($data);
    }

    //商家详情
    public function actionView($merchants_id){
        $query = MerchantsAccount::find();
        $query->where('id=:id', [':id'=>$merchants_id]);
        $query->with(['point' => function($query){
            return $query->where(['status'=>1]);
        }]);
        $model = $query->one();
        $data = [];
        if($model){
            $data['id'] = $model->id;
            $data['name'] = $model->name;
            $data['is_platform'] = $model->is_platform;
            $data['is_local'] = $model->is_local;
            $data['is_online'] = $model->is_online;
            $data['account'] = $model->account;
            $data['logo'] = $model->extends->logo;
            $data['image'] = $model->extends->image;
            $data['address'] = $model->extends->address;
            $data['phone'] = $model->extends->phone;
            $data['mobile'] = $model->extends->conact_mobile;
            $data['address'] = $model->extends->address;
            $data['longitude'] = $model->extends->longitude;
            $data['latitude'] = $model->extends->latitude;
            $data['slogan'] = $model->extends->slogan;
            $data['open_date'] = $model->extends->open_date;
            $data['peple_average'] = $model->extends->peple_average;
            $data['tags'] = MerchantsLocalTag::getTags($model->id);
            $data['exchange_point'] = ($model->point)?$model->point->id:0;

            $flags = [];
            //查找有没有兑换点
            $point = ExchangePoint::findOne(['merchants_id'=>$model->id, 'status'=>1]);
            if($point){
                $pointArr['key'] = 1;
                $pointArr['item'] = '兑换点';
                $flags = [$pointArr];
            }

            //查商家有没有优惠券
            $coupons = Coupons::findOne(['merchant_id'=>$model->id, 'type'=>1]);
            if($coupons){
                $flags[] = ['key'=>$coupons->id, 'item'=>$coupons->name];
            }

            $data['flags'] = $flags;
        }
        return CommonFun::returnSuccess($data);
    }

    //商家商品
    public function actionProducts($merchants_id){
        $model = MerchantsLocalProduct::getProducts($merchants_id);
        return CommonFun::returnSuccess($model);
    }

    //特色
    public function actionFeatures($id){
        $model = MerchantsLocalFeatures::find()->where('merchants_id=:mid and active=1', [':mid'=>$id])->asArray()->all();
        return CommonFun::returnSuccess($model);
    }

    //商家详情
    public function actionContent($merchants_id){
        $model = MerchantsAccountExtends::findOne($merchants_id);
        $data = [];
        if($model){
            $data['content'] = $model->content;
        }
        return CommonFun::returnSuccess($data);
    }


    //快捷结算
    public function actionPayment(){}

    //优惠券
    public function actionCoupons($id){
        $model = Coupons::find()
            ->select('id,name,amount,condition,type,limit')
            ->where('merchant_id=:mid and status=1', [':mid'=>$id])
            ->asArray()
            ->all();

        return CommonFun::returnSuccess($model);
    }
}
