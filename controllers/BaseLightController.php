<?php

namespace api\controllers;
use common\components\CommonFun;
use common\models\WeixinFans;
use Yii;
use common\components\CommonValidate;
use yii\web\Controller;
use common\controllers\RRKDBaseController;

//extends \yii\rest\Controller 
class BaseLightController extends  RRKDBaseController {
    public $layout = false;


    public $post = null;
    public $get = null;

    public $isGet = false;
    public $isPost = false;
    public $isAjax = false;

    public $token = null;

    public $openid;
    public $city_id;
    public $weixin;
    public $member_id;

    public $pageSize = 10;
    public $pageNum = 1;
    public $offset = 0;
    
    public function init() {
        $this->isAjax = CommonValidate::isAjax();
        $this->isPost = CommonValidate::isPost();
        $this->isGet = CommonValidate::isGet();

        //认证
        //$this->token =  Yii::$app->request->headers->get('token');
        //$this->openid = Yii::$app->request->headers->get('openid');
        
        
        //认证
        $this->token =  Yii::$app->request->get('token','');
        $this->openid = Yii::$app->request->get('openid','');

        if(empty($this->openid) || !$this->weixin = WeixinFans::getInfo($this->openid)){
            CommonFun::returnFalse('openid or weixininfo is empty');
        }

        $this->member_id = $this->weixin['member_id'];
       

        parent::init();
    }

}
