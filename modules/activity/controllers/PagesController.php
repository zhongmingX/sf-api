<?php

namespace app\modules\activity\controllers;

use common\components\CommonFun;
Use api\controllers\BaseController;
use common\models\ActivityPages;

/**
 * Default controller for the `activity` module
 */
class PagesController extends BaseController
{

    public function actionIndex($id){
        if(intval($id) == 0){
            return CommonFun::returnFalse('错误');
        }

        $data = [];
        $model = ActivityPages::find()->where(['id'=>$id, 'active'=>1])->one();
        if($model){
            $data['name'] = $model->name;
            $data['url'] = $model->url;
        }
        return CommonFun::returnSuccess($data);
    }
}
