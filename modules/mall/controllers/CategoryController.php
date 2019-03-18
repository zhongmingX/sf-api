<?php

namespace app\modules\mall\controllers;

use api\controllers\BaseController;
use common\components\CommonFun;
use common\models\Category;

class CategoryController extends BaseController {
	
	/**
	 * 在线商城分类
	 * @author RTS 2018年4月1日 13:42:48
	 */
	public function actionIndex() {
        $model = Category::getCategoryByCity(Category::TYPE_PRODUCT);
        return  CommonFun::returnSuccess($model);
	}
}
