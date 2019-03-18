<?php

namespace app\modules\exchange\controllers;

use api\controllers\BaseController;
use common\models\Category;
use common\components\CommonFun;
use common\models\Brand;

/**
 * Default controller for the `local` module
 */
class CategoryController extends BaseController
{
    public function actionIndex(){
//        $cityid = $this->city_id;
        $cityid = 0;
        $model = Category::getCategoryByCity(Category::TYPE_PRODUCT, $cityid);
        return  CommonFun::returnSuccess($model);
    }
    
    public function actionBrand(){
    	 $data = Brand::getAll();
    	 return  CommonFun::returnSuccess($data);
    }
}
