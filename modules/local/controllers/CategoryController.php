<?php

namespace app\modules\local\controllers;

use api\controllers\BaseController;
use common\models\Category;
use common\components\CommonFun;

/**
 * Default controller for the `local` module
 */
class CategoryController extends BaseController
{
    public function actionIndex($city_id){
        $model = Category::getCategoryByCity(Category::TYPE_MERCHANTS_ONOFF, $city_id);
        return  CommonFun::returnSuccess($model);
    }
}
