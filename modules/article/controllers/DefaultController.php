<?php

namespace app\modules\article\controllers;

use api\controllers\BaseController;
use common\models\Category;
use common\components\CommonFun;
use common\models\Article;
use common\models\CommonModel;

/**
 * Default controller for the `local` module
 */
class DefaultController extends BaseController
{
	/**
	 * 分类
	 * @author RTS 2018年5月18日 10:29:49
	 */
    public function actionCategory(){
        $model = Category::getCategoryByCity(Category::TYPE_ARTICLE);
        return  CommonFun::returnSuccess($model);
    }
    
    /**
     * 列表
     * @author RTS 2018年5月18日 10:30:02
     */
    public function actionList(){
    	$qurey = Article::find();
    	$qurey->where(['status'=>CommonModel::STATUS_ACTIVE]);
    	$qurey->filterWhere(['category_id'=>$this->request->get('category_id','')]);
    	$qurey->orderBy('update_time desc');
    	$count = $qurey->count('id');
    	$qurey->limit($this->pageSize)->offset($this->offset);
    	$data = $qurey->asArray()->all();
    	return CommonFun::returnSuccess(['total' => $count,'list' => $data,'page_size' => $this->pageSize,'page_num' => ++$this->pageNum]);
    }
    
    /**
     * 详情
     * @param RTS 2018年5月18日 10:31:00
     */
    public function actionDetails($id){
    	$query = Article::find()->where(['id'=>$id]);
    	$model = $query->one();
    	if(empty($model)){
    		return  CommonFun::returnFalse('获取数据失败。');
    	}
    	$model->clicks +=1;
    	$model->save();
    	$data = $query->asArray()->one();
    	return  CommonFun::returnSuccess($data);
    }
}
