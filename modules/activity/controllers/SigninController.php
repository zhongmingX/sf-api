<?php

namespace app\modules\activity\controllers;

use common\components\CommonFun;
Use api\controllers\BaseController;
use common\extend\OSS\Common;
use common\models\MembersCurrency;
use common\models\MembersSignin;
use common\models\MembersSigninRecord;
use api\controllers\MemberBaseController;

/**
 * Default controller for the `activity` module
 */
class SigninController extends MemberBaseController
{
    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionIndex()
    {
        $data['cond_days'] = 0;
        $data['today'] = false;
        $model = MembersSignin::findOne(['member_id'=>$this->member_id]);
        if($model){
            //判断今天有没有签到
            $time = getdate();
            $today_zero = mktime(0, 0, 0, $time['mon'], $time['mday'], $time['year']);
            if($model->last_sign_time > $today_zero){
                $data['today'] = true;
            }

            //判断昨日有没有签到，是否是连续签到
            if($model->last_sign_time > ($today_zero - 24 * 60 *60)){
                $data['cond_days'] = $model->cond_days;
            }else{
                $data['cond_days'] = 0;
            }
        }
        CommonFun::returnSuccess($data);
    }

    //签到
    public function actionCreate(){
        $res['result'] = false;
        if($this->isPost){
            $config = \Yii::$app->params['signin'];
            $time = getdate();
            $today_zero = mktime(0, 0, 0, $time['mon'], $time['mday'], $time['year']);

            $model = MembersSignin::findOne(['member_id'=>$this->member_id]);
            if(!$model){
                $model = new MembersSignin();
                $model->member_id = $this->member_id;
                $model->last_sign_time = 0;
                $model->cond_days = 0;
                $model->save();
            }
            if($today_zero < $model->last_sign_time && $model->last_sign_time < ($today_zero + 24*60*60)){
                //已经签
                $res['result'] = true;
                $res['is_signin'] = true;
            }else{
                //先记录签到日志
                $record = new MembersSigninRecord();
                $record->ctime = time();
                $record->member_id = $this->member_id;
                $record->coin = $config['coin'];
                $record->type = MembersSigninRecord::TYPE_NORMAL;
                $record->save();

                //加省币
                MembersCurrency::record($this->member_id, MembersCurrency::TYPE_INCR, MembersCurrency::SOURCE_SIGNIN, '', $config['coin']);

                //判断昨日有没有签到
                if(($today_zero - 24 * 60 *60) < $model->last_sign_time && $model->last_sign_time < $today_zero){
                    //判断连续签到天数
                    $model->cond_days += 1;
                    if($model->cond_days > 7){
                        $model->cond_days = 1;
                    }

                    //如果今天是第七天，那么省币在加一次
                    if($model->cond_days == $config['cond_days']){
                        $record = new MembersSigninRecord();
                        $record->ctime = time();
                        $record->member_id = $this->member_id;
                        $record->coin = $config['cond_coin'];
                        $record->type = MembersSigninRecord::TYPE_COND;
                        $record->save();

                        //加省币
                        MembersCurrency::record($this->member_id, MembersCurrency::TYPE_INCR, MembersCurrency::SOURCE_SIGNIN_COND, '', $config['cond_coin']);
                    }
                    $model->last_sign_time = time();
                }else{
                    $model->last_sign_time = time();
                    $model->cond_days = 1;
                }
                $model->save();
                $res['result'] = true;
                $res['is_signin'] = false;
            }

            CommonFun::returnSuccess($res);
        }
    }
}
