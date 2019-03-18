<?php

namespace app\modules\exchange\controllers;

use api\controllers\BaseController;
use common\models\Category;
use common\components\CommonFun;
use common\models\ExchangePoint;
use common\models\CommonModel;
use common\models\ExchangePointProduct;
use common\components\CommonFinance;
use common\models\ExchangeProduct;
use common\models\ExchangeProductContent;
use common\models\ExchangeProductSku;
use common\models\MerchantsProduct;
use common\models\MerchantsProductSku;
use common\models\ExchangePointProductSku;
use yii\db\Expression;

/**
 * Default controller for the `local` module
 */
class ProductController extends BaseController
{
	/**
	 * 获取商品列表  所有商品必须挂在兑换点上
	 * @param number $category_id 分类ID
	 * @param number $point_id 兑换点ID
	 */
    public function actionIndex(){
    	$category_id = CommonFun::getParams('category_id',0);
    	$brand_id = CommonFun::getParams('brand_id',0);
    	 
    	$point_id = CommonFun::getParams('point_id',0);
    	$order_by = CommonFun::getParams('order_by',' order,sf_exchange_product.ctime ');
    	$sort = CommonFun::getParams('sort',1);
    	$key = CommonFun::getParams('key','');
    	
    	$tags = CommonFun::getParams('tags','');

    	$is_coin = CommonFun::getParams('iscoin',0);
    	 
    	if(empty($point_id)){ //线上兑换点需要全国开放，定位有可能在其它城市,  如果定位城市有线上兑换点用线上的，如果没有拿总部的
    		//$this->region = '成都市';
            $point_id = 1000001;
    		$pointInfo = ExchangePoint::findOne(['city' => $this->region,'status' => CommonModel::STATUS_ACTIVE,'is_online' => 1]);
    		if($pointInfo){
                $point_id = $pointInfo['id'];
//    			return CommonFun::returnFalse('兑换点信息错误，请稍候再试。');
    		}
    	}
   
    	$qurey = ExchangePointProduct::find();
    	$qurey->where(['exchange_point_id' => $point_id, 'sf_exchange_point_product.status'=>1]);
    	
    	if(!empty($tags)){
    		$qurey->andWhere(new Expression("FIND_IN_SET($tags, tags)"));
    	}

    	$qurey->select = ['sf_exchange_point_product.id as exp_p_id','tags','product_id','exchange_point_id','sf_exchange_point_product.stock'];
    	$sort = $sort == 1 ? ' desc ':' asc ';
    	
    	
    	if(!empty($order_by)){
    		$order_by .= $sort;
    		if(CommonFun::startWith($order_by,'sf_exchange_point_product')){
    			$qurey->orderBy($order_by);
    			$order_by = '';
    		}
    	}

    	$qurey->joinWith(['product' => function($qurey) use ($category_id,$order_by,$key,$sort,$brand_id, $is_coin){
    		$qurey->select = ['sf_exchange_product.id','brand_id','title','sub_title','coin','platform_price','limit','category_id','market_price'];
    		$where = ['product_status' => ExchangeProduct::STATUS_PUT_ON];
            $qurey->where($where);

    		if(!empty($category_id)){
    		    //查看分类是不是一级分类
                $category = Category::getCategory($category_id);
                if($category){
                    if(count($category['child']) == 0){
                        $qurey->andWhere(['=', 'category_id', $category_id]);
                    }else{
                        $ids = [$category_id];
                        foreach ($category['child'] as $v){
                            $ids[] = $v['id'];
                        }
                        $qurey->andWhere(['in', 'category_id', $ids]);
                    }
                }
    		}

    		if(!empty($order_by)){
    			$qurey->orderBy($order_by);
    		}
    		
    		if(!empty($key)){
    			$qurey->andWhere(['like','title',$key]);
    		}
    		if(!empty($brand_id)){
    			$qurey->andWhere(['brand_id' => $brand_id]);
    		}

            //如果只需要纯省币
            if($is_coin == 1){
                $qurey->andWhere(['>', 'coin', 0]);
                $qurey->andWhere(['=', 'platform_price', 0]);
            }
    		
    		$qurey->with(['images'=>function ($qurey){
    			$qurey->where(['type' => 1]);
    		}]);

    	}]);
    	
    	$count = $qurey->count('exchange_point_id');
    	$qurey->limit($this->pageSize)->offset($this->offset);
    	$data = $qurey->asArray()->all();

        return CommonFun::returnSuccess(['total' => $count,'list' => $data,'page_size' => $this->pageSize,'page_num' => ++$this->pageNum]);
    }
    
    /**
     * 产品详情
     * @param number $point_id
     * @param number $product_id
     */
    public function actionDetails($point_id = 0,$product_id = 0){
        $point = ExchangePointProduct::find();
        $point->where(['product_id' => $product_id,'exchange_point_id' => $point_id]);
        $point->orderBy('status desc');
        $model = $point->one();
//    	$model = ExchangePointProduct::findOne(['product_id' => $product_id,'exchange_point_id' => $point_id]);
//    	CommonFun::pp($model);
    	if(empty($model)){
    		return CommonFun::returnFalse('对应数据未能找到！');
    	}
    	$condition = ['id' => $product_id,'product_status' => ExchangeProduct::STATUS_PUT_ON];
    	$query = ExchangeProduct::find();
    	$query->where($condition);
    	$query->select = ['title','sub_title','coin','market_price','limit','platform_price','id'];
    	$query->with(['images'=>function($query){
    		$query->where(['type' => 2,'status' => 1]);
    	}]);
    	$query->where($condition);
    	$data = $query->asArray()->one();
    	if(empty($data)){
    		return CommonFun::returnFalse('对应数据未能找到！！');
    	}
    	$data['stock'] = $model['stock'];
    	$data['exp_p_id'] = $model['id'];
    	$data['tags'] = $model['tags'];
    	$data['status'] = $model['status']; //覆盖状态
    	 
    	return CommonFun::returnSuccess($data);
    }

    /**
     * 获取商品详情
     * @param $product_id
     * @param $type 1手机端 2pc端
     */
    public function actionContent($product_id, $type = 1){
        $query = ExchangeProductContent::find()->where(['product_id'=>$product_id]);
        if($type == 1){
            $query->select('product_id, simple_desc, mobile_details');
        }else{
            $query->select('product_id, simple_desc, pc_details');
        }
        $data = $query->one()->toArray();
        return CommonFun::returnSuccess($data);
    }
    
    /**
     * 获取商品SKU 需要先在兑换点商品表获取主键 反查兑换点sku表
     * @param number $id
     * @author RTS 2018年4月5日 18:37:52
     */
    public function actionSku($id = 0,$point_id = 0){
    	$data = ExchangePointProduct::find()->where(['product_id' => $id,'exchange_point_id' => $point_id,'status'=> CommonModel::STATUS_ACTIVE])->select('id')->one();
    	if(empty($data)){
    		return CommonFun::returnFalse('商品信息获取失败，请稍候再试。');
    	}
    	$query = ExchangePointProductSku::find();
    	$query->where(['point_product_id' => $data['id'],'status'=> CommonModel::STATUS_ACTIVE]);

    	$query->with(['sku'=>function($query){
    		$query->select = ['name','item','id'];
    		$query->where = ['status' => 1];
    	}]);
    	$data = $query->asArray()->all();
    	if(empty($data)){
    		return CommonFun::returnSuccess();
    	}

    	if(empty($data[0]['sku'])){
    		return CommonFun::returnSuccess();
    	}

    	$tmp = $data[0]['sku']['name'];
    	$skuTps = explode(',', $tmp);
    	foreach ($skuTps as $k=>$item){
    		$sku_groups[$item] = MerchantsProductSku::doSku($data,$k);
    	}
    	return CommonFun::returnSuccess(['sku_groups' => $sku_groups]);
    }
    
    /**
     * 获取sku对应的价格
     * @param string $key
     * @param number $id
     * @author RTS 2018年4月3日 20:45:46
     */
    public function actionSkuPrice($key = '',$id = 0,$point_id = 0){
    	$query = ExchangeProductSku::find();
    	$key = strval($key);
    	$query->where(['product_id' => $id,'item' => $key,'status'=> CommonModel::STATUS_ACTIVE]);
    	//$query->select = [''];
    	$data = $query->asArray()->one();
    	if(empty($data)){
    		return CommonFun::returnFalse('数据获取失败，请稍候再试。');
    	}
    	$skuId = $data['id'];
    	$info = ExchangePointProduct::findOne(['product_id' => $id,'exchange_point_id' => $point_id,'status'=> CommonModel::STATUS_ACTIVE]);
    	if(empty($info)){
    		return CommonFun::returnFalse('兑换点数据获取失败，请稍候再试。');
    	}
    	
    	$info = ExchangePointProductSku::findOne(['point_product_id' => $info['id'],'product_sku_id' => $skuId,'status'=> CommonModel::STATUS_ACTIVE]);
    	if(empty($info)){
    		return CommonFun::returnFalse('兑换点SKU获取失败，请稍候再试。');
    	}
    	$data['stock'] = $info['stock'];
    	
    	return CommonFun::returnSuccess($data);
    }
}
