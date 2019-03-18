<?php

namespace api\controllers;
use common\components\CommonFun;
use common\models\PagesIndexArea;


class PagesController extends BaseController {
    function actionIndex($id){
        $model = PagesIndexArea::find()
            ->where('is_open=1 and id=:id', [':id'=>$id])
            ->with('items')
            ->asArray()
            ->one();
        CommonFun::returnSuccess($model);
    }
}
