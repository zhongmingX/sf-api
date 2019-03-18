<?php

namespace app\modules\cart\controllers;

use Yii;
use common\components\CommonFun;
use common\models\Cart;
use common\models\MerchantsProduct;
use common\models\CommonModel;
use common\models\ExchangePointProduct;
use common\models\MerchantsProductSku;
use common\models\ExchangeProductSku;
use common\models\ExchangeProduct;
use common\models\ExchangePointProductSku;
use api\controllers\MemberBaseController;

class DefaultController extends MemberBaseController {
	
	public function beforeAction($action) {
		parent::beforeAction($action);
		if ($this->isGet ) {
			return true;
		}
		$outPut = parent::check($action);
		if ($outPut === true) {
			return true;
		}
		
	}

	
	/**
	 * 编辑购物车
	 * @author RTS 2018年4月7日 13:37:35
	 */
	public function actionEdit(){
		$id = $this->request->post('id',0);
		$model = Cart::findOne(['member_id' => $this->member_id,'id' => $id,'status' => CommonModel::STATUS_ACTIVE]);
		if(empty($model)){
			return CommonFun::returnFalse('对应的数据错误，请稍候再试。');
		}
		
		$op_type = $this->request->post('op_type',1);
		if($op_type == 2){
			$model->status = CommonModel::STATUS_DELETE;
		}else{
			$quantity = intval($this->request->post('quantity',0));
			if(!empty($quantity)){
				$model->quantity = $quantity;
			}
			$comment = $this->request->post('comment','xxx');
			if($comment != 'xxx'){
				$model->comment = $comment;
			}
		}
		
		$res = $model->save();
		if($res){
			return CommonFun::returnSuccess();
		}
		return CommonFun::returnFalse('操作失败，请稍候再试。'.json_encode($model->getErrors()));
	}

	
	/**
	 * 购物车列表
	 * @author rts 2018年4月7日 13:06:54 
	 */
	public function actionList(){
		$condition = ['member_id' => $this->member_id,'status' => CommonModel::STATUS_ACTIVE];
		$data = Cart::find()->where($condition)->orderBy('ctime desc')->asArray()->all();
		return CommonFun::returnSuccess(['list' => $data]);
	}
	
	/**
	 * 加入购物车
	 * @author RTS 2018年4月7日 10:34:17
	 */
	public function actionAdd() {
		$type = intval($this->request->post('type',0));
		$product_id = intval($this->request->post('product_id',0));
		$quantity = intval($this->request->post('quantity',0));
		$exchange_point_id = intval($this->request->post('exchange_point_id',0));
		
		$sku_id = intval($this->request->post('sku_id',0));
		$sku_json = $this->request->post('sku_json','');

		$where = ['id' => $product_id,'status' => CommonModel::STATUS_ACTIVE];
		
		$exchange_point_product_id = 0;
		if($type == 0){
			$productInfo = MerchantsProduct::find()->where($where)->with(['images' => function($query){
				$query->where(['status' => 1,'type' => 1]);
			}])->asArray()->one();
			$stock = intval($productInfo['stock']);
			
		}else {
			$where = [
				'status' => CommonModel::STATUS_ACTIVE,
				'product_id' => $product_id,
				'exchange_point_id' => $exchange_point_id,
			];
			$info =  ExchangePointProduct::findOne($where);
			if(empty($info)){
				return CommonFun::returnFalse("兑换点：{$exchange_point_id}，未能找到对应的商品ID：{$product_id}，请稍候再试。");
			}
			$exchange_point_product_id = $info['id'];
			$stock = intval($info['stock']);
			$where = [
				'status' => CommonModel::STATUS_ACTIVE,
				'id' => $product_id,
			];
			
			$productInfo = ExchangeProduct::find()->where($where)->with(['images' => function($query){
				$query->where(['status' => 1,'type' => 1]);
			}])->asArray()->one();
			
		
		}
		
		if(empty($productInfo)){
			return CommonFun::returnFalse('获取商品信息失败，请稍候再试。');
		}
	
		$limit = intval($productInfo['limit']);
		$product_name = $productInfo['title'];

		if($quantity > $limit){
//			return CommonFun::returnFalse("加入购物车数量:{$quantity}大于限购数:{$limit}。");
            return CommonFun::returnFalse("商品当前购买次数已达上限,请选择其它商品！");
		}
	
		$object_id = $type == 0 ? $productInfo['merchants_id'] : $exchange_point_id;
		$coin = $type == 0 ? 0 : intval($productInfo['coin']);
		
		$product_img = '';
		if(!empty($productInfo['images'])){
			$product_img = $productInfo['images'][0]['src'];
		}
		
		$market_price = $productInfo['market_price'];
		$price = $productInfo['platform_price'];
		
		if(!empty($sku_id)){
			$where = [
				'status' => CommonModel::STATUS_ACTIVE,
				'product_id' => $product_id,
				'id'=> $sku_id,
			];
			if($type == 0){
				$skuInfo = MerchantsProductSku::findOne($where);
				if(empty($skuInfo)){
					return CommonFun::returnFalse('获取SKU信息失败，请稍候再试。');
				}
				$stock = intval($skuInfo['stock']);
			}else{//兑换点获取 库存 需要在点位上去取
				$info = ExchangePointProductSku::findOne(['point_product_id' => $exchange_point_product_id,'product_sku_id' => $sku_id,'status'=> CommonModel::STATUS_ACTIVE]);
				if(empty($info)){
					return CommonFun::returnFalse('兑换点SKU获取失败，请稍候再试。');
				}
				$stock = $info['stock'];
				$skuInfo = ExchangeProductSku::findOne($where);
				if(empty($skuInfo)){
					return CommonFun::returnFalse('获取SKU信息失败，请稍候再试。');
				}
			}
			$price = floatval($skuInfo['price']);
			$market_price = floatval($skuInfo['old_price']);
		}
		if($quantity > $stock){
			return CommonFun::returnFalse("加入购物车数量:{$quantity}大于库存数:{$stock}。");
		}

		$model = new Cart();
		$model->product_id = $product_id;
		$model->member_id = $this->member_id;
		
		$model->object_id = $object_id;
		$model->product_name = $product_name;
		$model->product_img = $product_img;
		$model->limit = $limit;
		$model->quantity = $quantity;
		$model->market_price = $market_price;
		$model->price = $price;
		$model->coin = $coin;
		$model->type = $type;
		$model->sku_id  = $sku_id;
		$model->sku_json  = $sku_json;
		$res = $model->save();
		if($res){
			return CommonFun::returnSuccess(['id' => $model->id]);
		}
		
		return CommonFun::returnFalse('操作失败，请稍候再试。'.json_encode($model->getErrors()));
	}
	
}
