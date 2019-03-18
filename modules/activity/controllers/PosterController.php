<?php
namespace app\modules\activity\controllers;
use \Yii;
use common\components\CommonFun;
use common\models\CommonModel;
use common\models\ActivityPoster;
use api\controllers\MemberBaseController;

class PosterController extends MemberBaseController{

    /**
     * 获取列表
     * @return unknown
     */
    public function actionList(){
        $lists = ActivityPoster::getList(['put_status' => CommonModel::STATUS_ACTIVE],$this->pageInfo,'sort desc',false,['id','name','img']);
        return CommonFun::returnSuccess(['rows' => $lists['data'],'counts' => $lists['count']]);
    }
    
    /**
     * 获取详情
     * @return unknown
     */
    public function actionDetails($id = 0){
        $id = intval($id);
        $res = ActivityPoster::getOne(['id' => $id],true);
        if($res){
            $res['qrcode_img'] = ActivityPoster::createQr($this->member_id,$res['img']);
        }
        return CommonFun::returnSuccess($res);
    }
    
    
}