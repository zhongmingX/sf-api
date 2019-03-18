<?php

namespace api\controllers;
use Yii;
use common\models\WeixinFans;
use common\components\CommonFun;



class MemberBaseController extends BaseController {
    
    //public $member_id;

    public function init() {
        parent::init();
        
        $this->member_id = Yii::$app->request->headers->get('uid');
        $this->openid = Yii::$app->request->headers->get('openid');
        if(!$this->member_id){
            if($this->openid){
                $this->weixin = WeixinFans::getInfo($this->openid);
            }
            $this->member_id = $this->weixin['member_id'];
        }
        
        if(!$this->member_id){
            return CommonFun::returnFalse('Not login');
        }
    }
}
