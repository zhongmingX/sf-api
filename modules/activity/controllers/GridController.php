<?php

namespace app\modules\activity\controllers;

use common\components\CommonFun;
Use api\controllers\BaseController;
use common\extend\OSS\Common;
use common\models\Config;
use common\models\MembersCurrency;
use common\models\MembersFinances;
use common\models\MembersRotaryGirdRecord;
use common\models\MembersRotaryGridAddress;
use common\models\MembersSignin;
use common\models\MembersSigninRecord;
use common\models\RotaryGridItems;
use common\models\MembersAddress;
use api\controllers\MemberBaseController;

/**
 * Default controller for the `activity` module
 */
class GridController extends MemberBaseController
{
    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionLists() //奖品列表
    {
        $model = RotaryGridItems::find()
            ->select('id,name,type,image,position')
            ->where('active = 1')
            ->orderBy('position asc')
            ->limit(8)
            ->asArray()
            ->all();

        return CommonFun::returnSuccess($model);
    }

    public function actionNumber(){
        $number = MembersRotaryGirdRecord::getNumbers($this->member_id);
        return CommonFun::returnSuccess($number);
    }

    public function actionStart(){
        if($this->isPost){
            $config = Config::getConfigs('luckygrid');
            $number = MembersRotaryGirdRecord::getNumbers($this->member_id);
            if($number['signin'] > 0){
                $category = 0;
            }else{
                $category = 1;
            }

            if($number['signin'] == 0 && $number['coin'] == 0){ //次数用完
                return CommonFun::returnFalse('抽奖次数不足');
            }

            if($category == 1){ //使用省币抽奖
                //验证省币是否够
                $model = MembersFinances::findOne(['member_id'=>$this->member_id]);
                $coin = CommonFun::doNumber($model->coin);
                if($coin < $config['coin']){
                    return CommonFun::returnFalse('省币不足抽奖');
                }

                //扣除省币
                MembersCurrency::record($this->member_id, MembersCurrency::TYPE_REDUCE, MembersCurrency::SOURCE_ROTARY_GRID_USE, '', $config['coin']);
            }

            //开始抽
            $model = RotaryGridItems::find()
                ->select('id,name,rate,coin,type,coupon_id')
                ->where('active = 1')
                ->orderBy('rate asc')
                ->limit(8)
                ->asArray()
                ->all();

            $data = [];
            $itemId = $this->getRand($model);
            foreach ($model as $v){
                if($v['id'] == $itemId){
                    $data = $v;
                }
            }

            if($data){
                //记录抽奖
                $model = new MembersRotaryGirdRecord();
                $model->member_id = $this->member_id;
                $model->category = $category;
                $model->item_id= $data['id'];
                $model->type = $data['type'];
                $model->title = $data['name'];
                $model->coin = $data['coin'];
                $model->coupon_id = $data['coupon_id'];
                if($model->type == RotaryGridItems::TYPE_PRODUCT){
                    $model->is_use  = 0;
                }else{
                    $model->is_use  = 1;
                }
                $model->ctime = time();

                $model->save();

                if($model->type == RotaryGridItems::TYPE_COIN && $model->coin > 0){ //省币
                    //加省币
                    MembersCurrency::record($this->member_id, MembersCurrency::TYPE_INCR, MembersCurrency::SOURCE_ROTARY_GRID, '', $model->coin);
                }else if($model->type == RotaryGridItems::TYPE_COUPON){
                    //优惠券，目前不存在
                }else if($model->type == RotaryGridItems::TYPE_PRODUCT){
                    //实物，发送短信

                }

                //返回ID
                unset($data['rate']);
            }
            return CommonFun::returnSuccess($data);
        }
    }

    //抽奖算法
    private function getRand($proArr){
        $arr = [];
        $proSum = 100000;
        foreach ($proArr as $k=>$v){
            if($k == 0){
                if($v['rate'] == 0){
                    $left = 0;
                }else{
                    $left = 1;
                }
            }else{
                $left = ($proArr[$k-1]['rate'] * 1000)+1;
            }

            $right = $proArr[$k]['rate'] * 1000;
            if(($k+1) == count($proArr)){
                $right = $proSum;
            }
            $arr[$k] = [
                'id' => $v['id'],
                'rate' => $v['rate'],
                'left' => $left,
                'right' => $right
            ];
        }
        $randNum = mt_rand(1, $proSum);
        foreach($arr as $v){
            if($randNum >= $v['left'] && $randNum <= $v['right']){
                return $v['id'];
            }
        }
    }

    //中奖名单
    public function actionNotice(){
        $data = [];
        $model = MembersRotaryGirdRecord::find()
            ->select('member_id, title')
            ->where("title <> '谢谢参与'")
            ->orderBy('ctime desc')
            ->limit('20')
            ->asArray()
            ->all();

        if($model){
            foreach ($model as $k=>$v){
                $data[$k] = $v;
                $data[$k]['member_id'] = substr_replace($v['member_id'], '****', 2, 3);
            }
        }

        return CommonFun::returnSuccess($data);
    }

    //获取用户转盘抽奖记录
    public function actionRecord(){
        $query = MembersRotaryGirdRecord::find()
            ->where('member_id=:mid', [':mid'=>$this->member_id]);

        $count = $query->count();
        $data = $query
            ->orderBy('ctime desc')
            ->offset($this->offset)
            ->limit($this->pageSize)
            ->asArray()
            ->all();
        if($data){
            foreach ($data as $k => $item){
                $data[$k]['ctime'] = date("Y-m-d H:i:s", $item['ctime']);
            }
        }

        return CommonFun::returnSuccess(['total' => $count,'list' => $data,'page_size' => $this->pageSize,'page_num' => ++$this->pageNum]);
    }

    //获取获奖商品详情
    public function actionRecordView($id){
        $model = MembersRotaryGirdRecord::find()
            ->where('id=:id',[':id'=>$id])
            ->with('address')
            ->asArray()
            ->one();

        return CommonFun::returnSuccess($model);
    }

    //设置收货地址
    public function actionSettingAddress(){
        if($this->isPost) {
            $id = \Yii::$app->request->post('id');
            //取默认收货地址
            $address = MembersAddress::getDefaultAddress($this->member_id);
            if(!$address){
                return CommonFun::returnFalse('未找到默认地址');
            }
            $model = MembersRotaryGridAddress::findOne(['r_id'=>$id]);
            if(!$model){
                $model = new MembersRotaryGridAddress();
            }

            $model->r_id = $id;
            $model->member_id = $this->member_id;
            $model->name = $address['name'];
            $model->mobile = $address['mobile'];
            $model->area = $address['area'];
            $model->address = $address['address'];
            $model->status = 1;
            if($model->save()){
                return CommonFun::returnSuccess();
            }
            return CommonFun::returnFalse('系统错误');
        }
    }
}
