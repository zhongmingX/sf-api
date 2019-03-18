<?php
namespace app\modules\member\controllers;
/**
 * Created by PhpStorm.
 * User: zhongming
 * Date: 2018/4/3 下午4:42
 */
use common\components\CommonValidate;
use common\models\MembersAddress;
use common\models\MembersBanks;
use common\models\MerchantsFreezes;
use gmars\sms\Sms;
use \Yii;
use common\components\CommonFun;
use common\models\Members;
use common\models\Alisms;
use common\models\MembersFavorite;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use api\controllers\MemberBaseController;

class AddressController extends MemberBaseController{

    //列表
    public function actionLists(){
        $lists = MembersAddress::getLists($this->member_id);
        return CommonFun::returnSuccess($lists);
    }

    //创建新的
    public function actionCreate(){
        if($this->isPost){
            $name = Yii::$app->request->post('name');
            $mobile = Yii::$app->request->post('mobile');
            $area = Yii::$app->request->post('area');
            $address = Yii::$app->request->post('address');
            $default = Yii::$app->request->post('default', 0);
            if(CommonFun::utf8_strlen($name) < 2 || !CommonValidate::isMobile($mobile) || !in_array($default, [0,1])){
                return CommonFun::returnFalse('name/mobile/default format error');
            }

            if(CommonFun::utf8_strlen($area) < 4 || CommonFun::utf8_strlen($address) < 5){
                return CommonFun::returnFalse('area/address format error');
            }

            $id = '';
            if($default == 1){ //查询默认
                $model = MembersAddress::getDefaultAddress($this->member_id);
                if($model){
                    $id = $model['id'];
                }
            }

            $m = new MembersAddress();
            $m->member_id = $this->member_id;
            $m->name = trim($name);
            $m->mobile = $mobile;
            $m->area = $area;
            $m->address = $address;
            $m->is_default = $default;
            $m->status = ($id)?$default:1; //如果是第一条数据不管什么情况都是默认
            if($m->save()){
                if($m->is_default == 1 && $id){
                    MembersAddress::updateAll(['is_default'=>0], ['id'=>$id]);
                }
                return CommonFun::returnSuccess();
            }
        }
        return CommonFun::returnFalse('member address create fail');
    }

    //更新
    public function actionUpdate(){
        if($this->isPost){
            $id = intval(Yii::$app->request->post('id'));
            $name = Yii::$app->request->post('name');
            $mobile = Yii::$app->request->post('mobile');
            $area = Yii::$app->request->post('area');
            $address = Yii::$app->request->post('address');
            $default = Yii::$app->request->post('default', 0);
            if(!$id){
                return CommonFun::returnFalse('id format error');
            }

            if(CommonFun::utf8_strlen($name) < 2 || !CommonValidate::isMobile($mobile) || !in_array($default, [0,1])){
                return CommonFun::returnFalse('name/mobile/default format error');
            }

            if(CommonFun::utf8_strlen($area) < 4 || CommonFun::utf8_strlen($address) < 5){
                return CommonFun::returnFalse('area/address format error');
            }

            $m = MembersAddress::findOne($id);
            if(!$m){
                return CommonFun::returnFalse('current address is not found');
            }

            $defaultId = '';
            if($default == 1){ //查询默认
                $model = MembersAddress::getDefaultAddress($this->member_id);
                if($model && $id != $model['id']){
                    $defaultId = $model['id'];
                }
            }

            $m->name = trim($name);
            $m->mobile = $mobile;
            $m->area = $area;
            $m->address = $address;
            $m->is_default = $default; //如果是第一条数据不管什么情况都是默认
            $m->status = 1;
            if($m->save()){
                if($m->is_default == 1 && $defaultId){
                    MembersAddress::updateAll(['is_default'=>0], ['id'=>$defaultId]);
                }
                return CommonFun::returnSuccess();
            }
        }
        return CommonFun::returnFalse('member address update fail');
    }

    //删除
    public function actionDelete(){
        if($this->isPost) {
            $id = intval(Yii::$app->request->post('id'));
            $m = MembersAddress::findOne($id);
            if(!$m){
                return CommonFun::returnFalse('current address is not found');
            }

            $m->status = MembersAddress::STATUS_DISABLED;
            if($m->save()){
                return CommonFun::returnSuccess();
            }
        }
        return CommonFun::returnFalse('member address delete fail');
    }

    //获取单个地址
    public function actionView($id){
        $data = MembersAddress::getData($this->member_id, $id);
        return CommonFun::returnSuccess($data);

    }

    //获取默认地址
    public function actionDefault(){
        $data = MembersAddress::getDefaultAddress($this->member_id);
        return CommonFun::returnSuccess($data);
    }

    //设置默认
    public function actionSetting(){
        if($this->isPost) {
            $id = intval(Yii::$app->request->post('id'));
            $m = MembersAddress::findOne($id);
            if(!$m){
                return CommonFun::returnFalse('current address is not found');
            }

            $defaultId = '';
            if($m->is_default != MembersAddress::DEFAULT_YES){ //查询默认
                $model = MembersAddress::getDefaultAddress($this->member_id);
                if($model && $id != $model['id']){
                    $defaultId = $model['id'];
                }
            }

            $m->is_default = MembersAddress::DEFAULT_YES;
            if($m->save()){
                MembersAddress::updateAll(['is_default'=>0], ['id'=>$defaultId]);
                return CommonFun::returnSuccess();
            }
        }
        return CommonFun::returnFalse('member address setting fail');
    }

}