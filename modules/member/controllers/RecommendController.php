<?php
namespace app\modules\member\controllers;
/**
 * Created by PhpStorm.
 * User: zhongming
 * Date: 2018/4/5 下午9:44
 */

use common\components\CommonFun;
use common\models\Config;
use common\models\MembersAttracts;
use api\controllers\BaseController;
use common\models\MerchantsAccount;
use common\models\Recommends;
use common\models\WeixinFans;
use \Yii;
use common\models\Members;
use abei2017\wx\Application;
use common\models\OssImg;
use api\controllers\MemberBaseController;

class RecommendController extends MemberBaseController{

    //推荐信息
    public function actionInfo(){
        $data = [];
        $data['num'] = 0;//Recommends::getCount(Recommends::TYPE_MEMBER, $this->member_id);
        $data['amount'] = 0;
        $data['qrcode'] = '';

        $query = Recommends::find()
            ->where('type=:type and obj_id=:oid and status=1',[':oid'=>$this->member_id, ':type'=>Recommends::TYPE_MEMBER]);
        $data['num'] = $query->count();

        $model = $query->all();

        if($model){
            $tmpAmount = 0;
            foreach ($model as $v){
                $tmpAmount += $v->amount;
            }
            $data['amount'] = CommonFun::doNumber($tmpAmount);
        }
        return CommonFun::returnSuccess($data);
    }

    //列表
    public function actionLists(){
        $data = [];
        $query = Recommends::find()
            ->where('type=:type and obj_id=:oid and status=1',[':oid'=>$this->member_id, ':type'=>Recommends::TYPE_MEMBER]);

        $data['total'] = $query->count();
        $data['page_size'] = $this->pageSize;
        $data['page_num'] = ++$this->pageNum;

        $model = $query->offset($this->offset)->limit($this->pageSize)->orderBy('ctime desc')->all();
        $data['lists'] = [];
        if($model){
            foreach ($model as $v){
                $data['lists'][] = [
                    'member_id' => substr_replace($v->member_id, '****', 2, 3),
                    'total_amount' => CommonFun::doNumber($v->total_amount),
                    'amount' => CommonFun::doNumber($v->amount),
                    'ctime' => date('Y-m-d', $v->ctime),
                    'coin' => $v->recommend_coin,
                ];
            }

        }

        return CommonFun::returnSuccess($data);
    }

//    //用户二维码
//    public function actionQrcode(){
//        $member = Members::findOne($this->member_id);
//        $data['url'] = '';
//        if($member){
//            if($member->recommend_qrcode){
//                $data['url'] = $member->recommend_qrcode;
//                return CommonFun::returnSuccess($data);
//            }else{
//                //获取推荐二维码
//                $wechat = \Yii::$app->wechat;
//                $qrcode = $wechat->createQrCode([
//                    'action_name' => 'QR_LIMIT_STR_SCENE',
//                    'action_info' => ['scene' => ['scene_str'=>'recommend:member_'.$member->id]]
//                ]);
//                $imgRawData = $wechat->getQrCodeUrl($qrcode['ticket']);
//                $member->recommend_qrcode = $imgRawData;
//                if($member->save()){
//                    return CommonFun::returnSuccess($data);
//                }
//            }
//        }
//        return CommonFun::returnFalse('member qrcode fail');
//    }

    /**
     * 微信二维码
     * @param int $type 0：服务号  1：小程序
     */
    public function actionQrcode($type = 0){
        $data['url'] = '';
        $query = WeixinFans::find()
            ->where('member_id=:mid', [':mid'=>$this->member_id]);
        if($type == 1){
            $query->andWhere(['=', 'is_mini', 1]);
        }else{
            $query->andWhere(['=', 'is_mini', 0]);
        }
        $weixinFans = $query->one();

        if($weixinFans){
            if($weixinFans->qrcode){
                $data['url'] = $weixinFans->qrcode;
                return CommonFun::returnSuccess($data);
            }else{
                if($type == 0){ //普通服务号
                    //获取推荐二维码
                    $wechat = \Yii::$app->wechat;
                    $qrcode = $wechat->createQrCode([
                        'action_name' => 'QR_LIMIT_STR_SCENE',
                        'action_info' => ['scene' => ['scene_str'=>'recommend:member_'.$this->member_id]]
                    ]);
                    $imgRawData = $wechat->getQrCodeUrl($qrcode['ticket']);
                    $weixinFans->qrcode = $imgRawData;
                    if($weixinFans->save()){
                        $data['url'] = $weixinFans->qrcode;
                        return CommonFun::returnSuccess($data);
                    }
                }else if($type == 1){
                    $config = Config::getConfigs('basic');
                    $conf = \Yii::$app->params['wxmini'];
                    $app = new Application(['conf'=>$conf]);
                    $qrcode = $app->driver("mini.qrcode");
                    $result = $qrcode->forever('pages/index/index?suid='.$this->member_id, $extra = ['is_hyaline'=>true]);
                    $name = './uploads/qrcode-uid-'.$this->member_id.'.png';
                    file_put_contents($name, $result);

                    $url = OssImg::upload($name);
                    if($url) {
                        @unlink($name);
                        $weixinFans->qrcode = $config['oss_host'].$url;
                        if($weixinFans->save()){
                            $data['url'] = $weixinFans->qrcode;
                            return CommonFun::returnSuccess($data);
                        }
                    }
                }
                return CommonFun::returnFalse('获取二维码错误');
            }
        }
        return CommonFun::returnFalse('微信信息不存在');
    }

}