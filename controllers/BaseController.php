<?php

namespace api\controllers;
use common\components\CommonFun;
use common\components\Func;
use common\extend\OSS\Common;
use common\models\Members;
use common\models\WeixinFans;
use Yii;
use common\models\Config;
use common\components\CommonValidate;
use yii\helpers\ArrayHelper;
use yii\filters\Cors;
use yii\web\Controller;
use common\controllers\RRKDBaseController;

//extends \yii\rest\Controller 
class BaseController extends  RRKDBaseController {
    public $layout = false;


    public $post = null;
    public $get = null;

    public $isGet = false;
    public $isPost = false;
    public $isAjax = false;

    public $token = null;
    public $region;
    public $lat;
    public $lon;
    public $openid;
    public $city_id;
    public $weixin;
    public $member_id = 0;
    public $location;
    public $api_source = '';

    
    public $pageSize = 10;
    public $pageNum = 1;
    public $offset = 0;

    public $pageInfo = [];
    public function init() {
        $this->isAjax = CommonValidate::isAjax();
        $this->isPost = CommonValidate::isPost();
        $this->isGet = CommonValidate::isGet();

        //过滤
        Yii::$app->request->setBodyParams(CommonFun::postCleanXss(Yii::$app->request->post()));

        //验证TOKEN
        $this->api_source = Yii::$app->request->headers->get('useFlag', '');
        if(in_array($this->api_source, ['miniprogram', 'alimini', 'H5'])){ //目前只验证小程序
            //验证TOKEN
            $this->token = Yii::$app->request->headers->get('token');
            $time = Yii::$app->request->headers->get('time');

            $key = 'www.shenglife.cn.wxapp';
            $validToken = md5($time . $key);
            if($validToken != $this->token){
                return CommonFun::returnFalse('TOKEN FAIL!');
            }
        }

        $this->region = urldecode(Yii::$app->request->headers->get('region'));
        $this->city_id = Yii::$app->request->headers->get('cityid');

//
//        $redis = Yii::$app->redis;
//        $redis->select(2); //2表
//        $location = $redis->get('location:'.$this->openid);
//        if($location){
//            $this->location = json_decode($location, true);
//        }
////        CommonFun::pp($this->location);
//        if($this->location){
//            $this->lon = $this->location['longitude'];
//            $this->lat = $this->location['latitude'];
//        }else{
//            $this->lat = Yii::$app->request->headers->get('lat');
//            $this->lon = Yii::$app->request->headers->get('lon');
//        }

        $this->lat = Yii::$app->request->headers->get('lat');
        $this->lon = Yii::$app->request->headers->get('lon');

       

		$this->pageNum = Yii::$app->request->get('pageNum',1);
		--$this->pageNum;
		$this->pageSize = Yii::$app->request->get('pageSize',10);
		$this->offset = $this->pageNum * $this->pageSize;
		
		$this->pageInfo = ['limit' => $this->pageSize,'page' => $this->pageNum];
		
        parent::init();
    }

    public function beforeAction($action)
    {
        parent::beforeAction($action);

        $this->post = yii::$app->request->post();
        $this->get = yii::$app->request->get();

        Yii::$app->params['basic'] = Config::getConfigs('basic'); //配置
        Yii::$app->params['signin'] = Config::getConfigs('signin'); //签到

        return $action;
    }

    /**
     * 返回数据
     * @param $data
     * @param $code
     * @param $message
     */
    public function resultData($data, $code = 200, $message = ''){
        Yii::$app->response->statusCode = $code;
        Yii::$app->response->statusText = $message;
        Yii::$app->response->data = $data;
        
//        CommonFun::returnSuccess(); //是否考虑 采用这种输出方式 灵活
    }
}
