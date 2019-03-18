<?php

namespace api\controllers;
use common\components\CommonFun;
use common\extend\alisdk\AopClient;
use common\extend\alisdk\request\AlipaySystemOauthTokenRequest;
use common\models\AlipayFans;
use common\models\Coupons;
use common\models\Members;
use common\models\MembersCurrency;
use common\models\WeixinFans;
use yii\web\Controller;
use common\models\MembersFinances;
use common\models\Recommends;
use common\models\Config;
use common\components\CommonValidate;
use common\models\Alisms;

class OauthController extends Controller {

    //小程序的授权/  有验证码登录部分
    function actionMiniprogram(){
        $mobile = trim(\Yii::$app->request->post('mobile', ''));
        $vercode = trim(\Yii::$app->request->post('vercode', ''));

        $code = \Yii::$app->request->post('code');
        $data = \Yii::$app->request->post('userdata');
        $suid = \Yii::$app->request->post('suid', '');

        CommonFun::log(json_encode(\Yii::$app->request->post()), 'mini', 'oauth');
        $member = null;
        if($mobile || $vercode){
            if(!CommonValidate::isMobile($mobile)){
                return CommonFun::returnFalse('帐号格式错误');
            }

            if(CommonFun::utf8_strlen($vercode) != 6){
                return CommonFun::returnFalse('验证码格式错误');
            }

            $cv = new \common\components\SendSms();
            if (!$cv->verifyValidate(Alisms::TYPE_LOGIN, (string)$mobile, $vercode)) {
                return CommonFun::returnFalse('验证码错误');
            }

            //查询帐号是否存在
            $member = Members::find()
                ->where('mobile=:m and status=:s', [':m'=>$mobile, ':s'=>Members::STATUS_NORMAL])
                ->one();
        }

        $appid = \Yii::$app->params['wxmini']['app_id'];
        $appSecret = \Yii::$app->params['wxmini']['secret'];
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=APPID&secret=SECRET&js_code=JSCODE&grant_type=authorization_code';
        $url = str_replace('APPID', $appid, $url);
        $url = str_replace('SECRET', $appSecret, $url);
        $url = str_replace('JSCODE', $code, $url);

        $res = CommonFun::curlGet($url);
        $res = json_decode($res, true);
        CommonFun::log($res, 'mini', 'oauth');
        if(isset($res['openid'])){
            $memberid = 0;

            //如果是手机号登录成功，
            if($member){
                $memberid = $member->id;
            }

            //验证unionid是否存在
            if(isset($res['unionid']) && $memberid == 0){
                $wxTmp = WeixinFans::getUnionid($res['unionid']);
                if($wxTmp){
                    $memberid = $wxTmp->member_id;
                }
            }

            //验证openid是否存在
            $wxFans = WeixinFans::getInfo($res['openid']);
            if($wxFans){
                if(!$member){
                    $memberid = $wxFans->member_id;
                }

                if ($wxFans->unionid == ''){ //unionid是否存在
                    if(isset($res['unionid'])){
                        $wxFans->unionid = $res['unionid'];
                        $wxFans->save();
                    }
                }
            }

            //用户不存在时
            if(isset($data['nickName'])){
                $data['nickname'] = CommonFun::filter_Emoji($data['nickName']);
            }else{
                $data['nickname'] = '微信用户';
            }
            $data['sex'] = isset($data['gender'])?$data['gender']:9;
            if($memberid == 0){
                //看是否有推荐
                $is_recommend = false;
                $recommend_id = 0;
                if($suid != ''){
                    $tmp = explode('_', $suid);
                    if((isset($tmp[0]) && in_array($tmp[0], ['member', 'merchants'])) && isset($tmp[1]) && intval($tmp[1]) > 0){

                        if($tmp[0] == 'member'){
                            $is_recommend = Recommends::TYPE_MEMBER;
                        }else{
                            $is_recommend = Recommends::TYPE_MERCHANTS;
                        }
                        $recommend_id = $tmp[1];
                    }
                }
                $memberid = Members::create($data, $is_recommend, $recommend_id);
            }
//            else{
//                //是否帐号登录
//                if($mobile){
//                    //帐号存在
//                    $member = Members::findOne($memberid);
//                    if($member->mobile == ''){
//                        $member->mobile = $mobile;
//                        $member->save();
//                    }
//                }
//            }

            if(!$wxFans && $memberid > 0){
                $data['unionid'] = isset($res['unionid'])?$res['unionid']:'';
                WeixinFans::create($res['openid'], $memberid, $data, 1);
            }


            //当有两个帐号的时候合并
            $uid = \Yii::$app->request->headers->get('uid', 0);
            if($uid > 0 && $uid != $memberid){
                $url = \Yii::$app->request->hostInfo.CommonFun::url(['/member/common/account-merge']);
                $data['from'] = $uid;
                $data['to'] = $memberid;
                $data['token'] = CommonFun::md5($uid.$memberid.'shenglife@');
                $ress = CommonFun::curlPost($url, $data, 15, ['uid:'.$uid]);
                CommonFun::log([$data, $ress], 'merge', 'oauth');
            }
        }

        unset($res['session_key']);
        $res['uid'] = $memberid;
        $res['result'] = Members::getInfo($memberid, 'miniprogram');
        return CommonFun::returnSuccess($res);
        exit;
    }

    /**
     * 支付宝小程序授权
     * @param $code
     */
    function actionAlimini(){
        $code = \Yii::$app->request->post('code');
//        $mobile = trim(\Yii::$app->request->post('mobile', ''));
//        $vercode = trim(\Yii::$app->request->post('vercode', ''));
//
//        $member = null;
//        if($mobile || $vercode){
//            if(!CommonValidate::isMobile($mobile)){
//                return CommonFun::returnFalse('帐号格式错误');
//            }
//
//            if(CommonFun::utf8_strlen($vercode) != 6){
//                return CommonFun::returnFalse('验证码格式错误');
//            }
//
//            $cv = new \common\components\SendSms();
//            if (!$cv->verifyValidate(Alisms::TYPE_LOGIN, (string)$mobile, $vercode)) {
//                return CommonFun::returnFalse('验证码错误');
//            }
//
//            //查询帐号是否存在
//            $member = Members::find()
//                ->where('mobile=:m and status=:s', [':m'=>$mobile, ':s'=>Members::STATUS_NORMAL])
//                ->one();
//        }

        $c  = new AopClient();
        $c->rsaPrivateKey = \Yii::$app->params['alimini']['private_key'];
        $c->alipayrsaPublicKey = \Yii::$app->params['alimini']['public_key'];
        $c->appId = \Yii::$app->params['alimini']['app_id'];
        $c->signType = 'RSA2';
        $c->format = 'json';

//        获取用户ID
        $request = new AlipaySystemOauthTokenRequest();
        $request->setGrantType('authorization_code');
        $request->setCode($code);

        $res = $c->execute($request);
        if(isset($res->alipay_system_oauth_token_response->user_id)){
            CommonFun::log($res, 'alimini', 'oauth');
            $alipay_user_id = $res->alipay_system_oauth_token_response->user_id;

            $memberid = 0;
            //查询支付宝帐号
            $aliFans = AlipayFans::findOne(['user_id'=>$alipay_user_id]);
            if($aliFans){
                $memberid = $aliFans->member_id;
            }

            if($memberid == 0){
                $suid = \Yii::$app->request->post('suid', '');
                //看是否有推荐
                $is_recommend = false;
                $recommend_id = 0;
                if($suid != ''){
                    $tmp = explode('_', $suid);
                    if((isset($tmp[0]) && in_array($tmp[0], ['member', 'merchants'])) && isset($tmp[1]) && intval($tmp[1]) > 0){

                        if($tmp[0] == 'member'){
                            $is_recommend = Recommends::TYPE_MEMBER;
                        }else{
                            $is_recommend = Recommends::TYPE_MERCHANTS;
                        }
                        $recommend_id = $tmp[1];
                    }
                }
                $data['nickname'] = 'alipay'.$alipay_user_id;
                $memberid = Members::create($data, $is_recommend, $recommend_id);
            }

            //查询支付宝帐号
            if(!$aliFans){
                $aliFans = new AlipayFans();
                $aliFans->user_id = $alipay_user_id;
                $aliFans->member_id = $memberid;
                $aliFans->ctime = time();
                $aliFans->save();
            }

            return CommonFun::returnSuccess(['uid' => $memberid]);
        }else{
            return CommonFun::returnFalse($res->error_response->sub_msg);
        }
    }
}
