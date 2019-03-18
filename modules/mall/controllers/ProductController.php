<?php

namespace app\modules\mall\controllers;

use Yii;
use api\controllers\BaseController;
use common\components\CommonFun;
use common\models\Category;
use common\models\MerchantsProduct;
use common\models\CommonModel;
use common\models\MerchantsProductSku;
use common\models\MerchantsProductComment;
use callmez\wechat\sdk\mp\Merchant;
use common\models\MerchantsAccount;
use common\models\MembersFavorite;

class ProductController extends BaseController {
	
	/**
	 * 商城商品
	 * @author RTS 2018年4月1日 13:52:01
	 */
	public function actionIndex() {
		$categoryId = intval(CommonFun::getParams('category_id',0));
		$merchantsId = intval(CommonFun::getParams('merchants_id',0));
		$key = Yii::$app->request->get('key','');

		$query = MerchantsProduct::find();
		$query->where(['status' => CommonModel::STATUS_ACTIVE,'product_status' => MerchantsProduct::STATUS_PUT_ON]);
		if(!empty($categoryId)){
			$query->andWhere(['category_id' => $categoryId]);
		}
		
		if(!empty($merchantsId)){
			$query->andWhere(['merchants_id' => $merchantsId]);
		}
		
		if(!empty($key)){
			$query->andWhere(['like','title',$key]);
		}
		$order = CommonFun::getParams('order_by','ctime');
		$sort = Yii::$app->request->get('sort',1);
		$sort = $sort == 1 ? ' desc ':' asc ';
		$order.= $sort;
		$query->orderBy($order);
		$query->select('title,sub_title,platform_price,market_price,brand_id,category_id,merchants_id,id,stock');
		
		$query->with(['category' => function ($query){
			$query->select('id','name');
		},'merchants' => function ($query){
			$query->select = ['id','name','is_platform'];
		},'brand'=>function ($query){
			$query->select = ['id','name'];
		},'images'=>function ($query){
			$query->where(['type' => 1]);
		}]);
		
		$total = $query->count(); 
		$query->limit($this->pageSize)->offset($this->offset);
    	$data = $query->asArray()->all();
    	return CommonFun::returnSuccess(['total' => $total,'list' => $data,'page_size'=>$this->pageSize,'page_num' => ++$this->pageNum]);
	}
	
	/**
	 * 商城商品详情
	 * @author RTS 2018年4月2日 10:18:38
	 */
	public function actionBasic($id = 0) {
		$query = MerchantsProduct::find();
		$query->where(['id' => $id,'status'=>1,'product_status' => MerchantsProduct::STATUS_PUT_ON]);
		$query->select('title,sub_title,platform_price,market_price,id,limit,stock,merchants_id');
		
		$query->with(['images'=>function ($query){
			$query->where(['type' => 2]);
		},'content'=>function ($query){
			$query->select = ['mobile_details'];
		}]);
		
		$data = $query ->asArray()->one();
		return CommonFun::returnSuccess($data);
		
	}
	
	/**
	 * 获取商品SKU
	 * @param number $id
	 * @author RTS 2018年4月3日 20:45:37
	 */
	public function actionSku($id = 0){
		$query = MerchantsProductSku::find();
		$query->where(['product_id' => $id,'status'=> CommonModel::STATUS_ACTIVE]);
		$data = $query->asArray()->all();
		if(empty($data)){
			return CommonFun::returnSuccess();
		}
		$tmp = $data[0]['name'];
		if(empty($tmp)){
			return CommonFun::returnSuccess();
		}
		$skuTps = explode(',', $data[0]['name']);
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
	public function actionSkuPrice($key = '',$id = 0){
		$query = MerchantsProductSku::find();
		$query->where(['product_id' => $id,'item' => $key,'status'=> CommonModel::STATUS_ACTIVE]);
		$data = $query->asArray()->one();
		return CommonFun::returnSuccess($data);
	}
	
	
	
	/**
	 * 获取商品评价
	 * @param number $id
	 */
	public function actionComments($id = 0){
		$query = MerchantsProductComment::find();
		$query->select = ['score','content','ctime','member_id'];
		$query->where(['product_id' => $id,'display' => 1,'status'=> CommonModel::STATUS_ACTIVE]);
		$query->orderBy('ctime desc');
		$query->with(['member'=>function ($query){
			$query->select = ['nickname','id'];
		}]);
		
		
		$total = $query->count();
		$query->orderBy('ctime desc')->limit($this->pageSize)->offset($this->offset);
		$data = $query->asArray()->all();
		return CommonFun::returnSuccess(['total' => $total,'list' => $data,'page_size'=>$this->pageSize,'page_num' => ++$this->pageNum]);
	}
	
	
	/**
	 * 获取商品商户信息
	 * @param number $id
	 */
	public function actionMerchants($merchants_id = 0){
		$query = MerchantsAccount::find();
		$query->select = ['name','id'];
		$query->where(['id' => $merchants_id]);
		$query->with(['extends'=>function ($query){
			$query->select = ['logo','id'];
		}]);

		$data = $query->asArray()->one();
		if(!empty($data)){
			$query = MerchantsProduct::find();
			$query->where(['merchants_id' => $merchants_id,'status'=> CommonModel::STATUS_ACTIVE,'product_status' => MerchantsProduct::STATUS_PUT_ON]);
			$sell_counts = $query->count('id');

			$focus = MembersFavorite::getCounts($merchants_id,MembersFavorite::TYPE_MERCHANTS);
			
			$data['feedback_rate'] = '100%';
			$data['focus'] = $focus;
			$data['sell_counts'] = $sell_counts;	
		}
		return CommonFun::returnSuccess($data);
	}
	
}
