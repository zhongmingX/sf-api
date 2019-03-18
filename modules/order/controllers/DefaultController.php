<?php

namespace app\modules\order\controllers;

use common\extend\OSS\Common;
use common\models\AlipayFans;
use common\models\MembersCoupon;
use common\models\MiniTemplateinfo;
use common\models\pay\AliMiniService;
use common\models\pay\AlipayService;
use common\models\pay\WxMiniService;
use Yii;
use api\controllers\BaseController;
use common\components\CommonFun;
use common\models\Cart;
use common\models\MerchantsProduct;
use common\models\CommonModel;
use common\models\ExchangePointProduct;
use common\models\MerchantsProductSku;
use common\models\ExchangeProductSku;
use common\models\MembersAddress;
use common\models\Orders;
use common\components\enum\OrderEnum;
use callmez\wechat\sdk\mp\Merchant;
use common\models\MerchantsAccount;
use common\models\ExchangePoint;
use common\models\ExchangeProduct;
use PetstoreIO\Order;
use yii\helpers\ArrayHelper;
use common\models\pay\WxPayService;
use common\models\Members;
use common\models\OrdersPay;
use common\models\MembersCurrency;
use common\components\FinanceSign;
use common\models\OrdersComments;
use common\models\MembersFinances;
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
	 * 购物车生成订单
	 * @author RTS 2018年4月8日 09:02:52
     * update: 2018年8月8日  source 添加订单来源（微信/小程序/支付宝...) see OrderEnum::PAY_TYPE
	 */
	public function actionAddForCart() {
		$ids = $this->request->post('id','');
		$address_id = intval($this->request->post('address_id',0));
		$address_info = $this->checkAddressInfo($address_id);
		$idArr = explode(',', $ids);
		$query = Cart::find()->where(['in' , 'id' , $idArr]);
		$query->andWhere(['status' => 1,'member_id' => $this->member_id]);
		$data = $query->all();
		if(empty($data)){
			return CommonFun::returnFalse('对应的数据错误，请稍后再试。');
		}
		
		$return = [];
		$orderModel = new Orders();
		foreach ($data as $item){
			$order = [];
			$order['type'] = $item['type'] + 1;
			$order['member_id'] = $item['member_id'];
			$order['product_id'] = $item['product_id'];
			$order['order_obj_id'] = $item['object_id'];
			
			$order['title'] = $item['product_name'];
			$order['image'] = $item['product_img'];
			$order['product_amount'] = $item['quantity'] * $item['price'];
			$order['product_coin'] = $item['quantity'] * $item['coin'];
			$order['comment'] = $item['comment'];

			$order['shipping_fee'] = floatval(CommonFun::getArrayValue(Yii::$app->params['basic'],'shipping_fee',0));//配送费用
			$order['order_amount'] = $order['product_amount'] + $order['shipping_fee'];
			$order['order_coin'] = $order['product_coin'];

		
			$order['shipping_type'] = OrderEnum::SHIPPING_TYPE_PLATFROM;
			$order['shipping_name'] = OrderEnum::$SHIPPING_TYPE[OrderEnum::SHIPPING_TYPE_PLATFROM];

            $source = ($this->request->post('source'))?$this->request->post('source'):0;
            $order['pay_type'] = $source;
            $order['pay_name'] = OrderEnum::$PAY_TYPE[$source];

			$product_info = [
				'product_id' => $item['product_id'],
				'product_name' => $item['product_name'],
				'market_price' => $item['market_price'],
				'platfrom_price' => $item['price'],
				'platfrom_coin'=> $item['coin'],
				'number' => $item['quantity'],
				'sku_ids' => $item['sku_id'],
				'sku_json' => $item['sku_json'],
				'status' => 1
			];
			$orderId = 0;
			$cache_name = $this->member_id.'_'.$item['product_id'].'_'.$item['sku_id'].'_'.$item['object_id'].'_'.$order['type'];
			$res = $orderModel->create($order,$product_info,$address_info,$orderId,$cache_name);
			if($res === true){
				$cache_value = intval(CommonFun::getCache($cache_name));
				$cache_value = CommonFun::doNumber($cache_value,$item['quantity'],'+');
				CommonFun::setCache($cache_name,$cache_value,2);
				$res = $orderId;
			}
			
			$return[] = [$item['id'] => $res];
			if($orderId > 0){
				$item->status = CommonModel::STATUS_DELETE;
				$item->save();
			}
			//sleep(1);
			//TODO member_coin  订单返用户省币   shipping_type 配送方式(自提/商家配送/平台配送/其它)  shipping_name 配送名称	
		}
		return CommonFun::returnSuccess($return);
	}
	
	/**
	 * 直接结算生成订单
	 * @author RTS 2018年4月8日 14:23:11
     * update: 2018年8月8日  source 添加订单来源（微信/小程序/支付宝...) see OrderEnum::PAY_TYPE
	 */
	public function actionAddForSettlement() {
		$money = floatval($this->request->post('money',0.01));
		$merchants_id = intval($this->request->post('merchants_id',0));
		$comment = $this->request->post('comment','');
		$res = MerchantsAccount::find()->where(['id' => $merchants_id])->select('id')->with(['extends' => function ($q){
			$q->select = ['id','logo'];
		}])->asArray()->one();

		if(empty($res)){
			return CommonFun::returnFalse('商户信息错误，请稍候再试。');
		}

		$orderModel = new Orders();
		$order['type'] = OrderEnum::TYPE_ONLINEOFF_PAY;
		$order['member_id'] = $this->member_id;
		$order['product_id'] = 0;
		$order['order_obj_id'] = $merchants_id;
			
		$order['title'] = '结算订单';
		$order['image'] = CommonFun::getArrayValue($res['extends'],'logo');
		$order['product_amount'] = $money;
		$order['product_coin'] = 0;
		$order['comment'] = $comment;

        $order['member_coin'] = 0;//订单返用户省币
        $order['shipping_fee'] = 0;//配送费用
        $order['order_amount'] = CommonFun::doNumber($money, $order['shipping_fee'], '+');
        $order['order_coin'] = $order['product_coin'];

		$order['shipping_type'] = OrderEnum::SHIPPING_TYPE_SELF;
		$order['shipping_name'] = OrderEnum::$SHIPPING_TYPE[OrderEnum::SHIPPING_TYPE_SELF];

        $source = ($this->request->post('source'))?$this->request->post('source'):0;
		$order['pay_type'] = $source;
		$order['pay_name'] = OrderEnum::$PAY_TYPE[$source];

		$orderId = 0;
		$res = $orderModel->create($order,[],[],$orderId);
        CommonFun::log([$order,$res, $orderId],__FUNCTION__,'orders');
		if($res === true){
			$res = $orderId;
			return CommonFun::returnSuccess(['id' => $res]);
		}
		
		return CommonFun::returnFalse('生成订单失败，请稍候再试。');
	}
	
	private function checkAddressInfo($address_id = 0){
		$address_info = MembersAddress::find()->where(['id' => $address_id,
				'member_id' => $this->member_id
				])->asArray()->one();
		if(empty($address_info)){
			return CommonFun::returnFalse('地址信息错误，请稍后再试。');
		}
		return $address_info;
	}
	
	/**
	 * 兑换生成订单
	 * @author RTS 2018年4月8日 15:05:32
     * update: 2018年8月8日  source 添加订单来源（微信/小程序/支付宝...) see OrderEnum::PAY_TYPE
	 */
	public function actionAddForExchange() {
		$product_info = $this->request->post('product_info',[]);
		$exchange_point_id = intval($this->request->post('exchange_point_id',0));
		$comment = $this->request->post('comment','');

		$return = [];

		$orderModel = new Orders();
		foreach ($product_info as $info){
			
			$pid = $info['pid'];
			$quantity = $info['quantity'];
			$item = ExchangePointProduct::find()->where(['exchange_point_id' => $exchange_point_id,'product_id' => $pid,'status' => CommonModel::STATUS_ACTIVE])->select('id')->asArray()->one();
			if(empty($item)){
				return CommonFun::returnFalse('商品ID:'.$pid.'兑换点信息不存在，请稍候再试。');
			}

			//update 2018.11.16 21:21 胡
            //处理用户纯省币限制
            $memberCoinNums = Orders::getMemberExchangeCoinNums($this->member_id, $exchange_point_id);
            if($memberCoinNums !== true){
                return CommonFun::returnFalse($memberCoinNums);
            }
			
			$query = ExchangeProduct::find();
			$condition = ['id' => $pid,'product_status' => ExchangeProduct::STATUS_PUT_ON,'status' => CommonModel::STATUS_ACTIVE];
			$query->where($condition);
			$query->select = ['title','sub_title','coin','market_price','limit','platform_price','id'];
			$query->with(['images' => function($query){
				$query->where(['type' => 1,'status' => 1]);
				$query->select = ['id','product_id','src'];
			}]);
		
			$item = $query->asArray()->one();
			if(empty($item)){
				return CommonFun::returnFalse('商品ID:'.$pid.'信息不存在，请稍候再试。');
			}
			

			$order = [];
			$order['type'] = OrderEnum::TYPE_EXCHANGE_OFF;
			$order['member_id'] = $this->member_id;
			$order['product_id'] = $pid;
			$order['order_obj_id'] = $exchange_point_id;
			
			$order['title'] = $item['title'];
			$order['image'] = !empty($item['images']) ? $item['images'][0]['src'] : '';
			
		
			
			$order['product_amount'] = $quantity * $item['platform_price'];
			$order['product_coin'] =  $quantity * $item['coin'];
			$order['comment'] = $comment;

			$order['shipping_fee'] = 0;//配送费用
			$order['order_amount'] = $order['product_amount'] + $order['shipping_fee'];
			$order['order_coin'] = $order['product_coin'];

			$order['shipping_type'] = OrderEnum::SHIPPING_TYPE_SELF;
			$order['shipping_name'] = OrderEnum::$SHIPPING_TYPE[OrderEnum::SHIPPING_TYPE_SELF];

            $source = ($this->request->post('source'))?$this->request->post('source'):0;
            $order['pay_type'] = $source;
            $order['pay_name'] = OrderEnum::$PAY_TYPE[$source];
			
			$product_info = [
				'product_id' => $pid,
				'product_name' => $item['title'],
				'market_price' => $item['market_price'],
					'platfrom_price' => $item ['platform_price'],
					'platfrom_coin' => $item ['coin'],
					'number' => $quantity,
					'sku_ids' => 0,
					'sku_json' => '',
					'status' => 1 
			];
			
			$orderId = 0;
			$res = $orderModel->create($order,$product_info,[],$orderId);
			if($res === true){
				$res = $orderId;
			}
			$return[] = [$pid => $res];	
		}
		return CommonFun::returnSuccess($return);
	}
	
	/**
	 * 订单列表
	 * @author RTS 2018年4月16日 10:28:11
	 */
	public function actionList(){
		$order_status = CommonFun::getParams('order_status','');
		$type = CommonFun::getParams('type','');
		$where = [
			'member_id' => $this->member_id,
			'status' => 1,
		];
		if(!empty($type)){
			if(in_array($type,[2,21])){
				$type = [2,21];
			}
			$where['type'] = $type;
		}
		
		$qwhere = Orders::getWhere($order_status);
		$where = ArrayHelper::merge($qwhere, $where);

		$res = Orders::getList($where,$this->pageSize,$this->offset);
		return CommonFun::returnSuccess([
				'total' => $res['total'],
				'list' => $res['data'],
				'page_size' =>$this->pageSize,
				'page_num' => ++$this->pageNum
		]);
	}
	
	
	/**
	 * 订单详情
	 * @author RTS 2018年4月17日 09:20:38
	 */
	public function actionDetails($id = 0){
		$where = [
			'member_id' => $this->member_id,
			'id' => $id,
		];
		$data = Orders::details ( $where );
		
		return CommonFun::returnSuccess ( $data );
	}
	
	/**
	 * 支付订单
	 * 
	 * @param number $orderId
     * update: 2018年8月8日  OrderEnum::PAY_TYPE 按支付类型 返回不同支付场景
     * create: 2018年9月1日 coupon_id  增加优惠券ID 用户可以使用优惠券抵扣
	 */
	public function actionPay() {
        $data = [];
		$is_use_balance = $this->request->post ( 'is_use_balance',0 );
		if($this->api_source == 'alimini'){ //支付宝过来的特殊处理，它的my.httpRequest POST是不支持数组模式
		    $data[0]['id'] = $this->request->post ( 'id', 0);
		    $data[0]['coupon_id'] = $this->request->post ( 'coupon_id', 0);
        }else{
            $data = $this->request->post ( 'data', []);
        }
		if (empty ( $data ) || ! is_array ( $data )) {
			return CommonFun::returnFalse ( '接收数据为空或格式错误' );
		}
		$info = Members::getFinances ( $this->member_id );
		if (empty ( $info )) {
			return CommonFun::returnFalse ( '用户财务信息获取失败。' );
		}

		$totalMoney = 0;
		$totalCoin = 0;
		$totalBalanceAmount = 0;
		$trade_number = WxPayService::createTradeNumber ();
		$where = [ 
			'member_id' => $this->member_id,
			'payment_status' => OrderEnum::PAY_STATUS_UNPAY
		];
		
		$orderArr = [];
		$userBalance = $info['balance'];

        //当同一个用户进来后 所有支付方式应该是相同的，所以取一个值
		$pay_type = 0;

        $ids = []; //订单ID组
		foreach ( $data as $item ) {
			$where ['id'] = $item ['id'];
			$is_use_coin = intval(CommonFun::getArrayValue($item,'is_use_coin',0));

			$order = Orders::details ( $where );

			if (empty ( $order )) {
				return CommonFun::returnFalse ( '订单：'.$item['id'].'数据获取失败。');
			}
			if($order['order_status'] > OrderEnum::ORDER_STATUS_PAYING){
				return CommonFun::returnFalse ( '订单：'.$item['id'].'状态已发生改变，请刷新页面后再试。');
			}

            $pay_type = $order['pay_type'];

            // 2018.9.1 优惠券处理
            $coupon = null;
            $coupon_id = (isset($item ['coupon_id'])?$item ['coupon_id']: 0);
            if($coupon_id > 0){
                $coupon = MembersCoupon::find()
                    ->where('is_use=0 and status=1 and id=:id', [':id'=>$coupon_id])
                    ->one();

                if(!$coupon){
                    return CommonFun::returnFalse('优惠券已使用或者不存在');
                }
            }

            if($coupon){
                $order = Orders::findOne($item['id']);
                $order->coupons_id = $coupon->id;
                $order->coupons_category = $coupon->info->category;
                $order->coupons_name = $coupon->info->name;
                if($coupon->info->type == 1){
                    $order->coupons_amount = $order->order_amount * (1 - ($coupon->amount/10));
                }else{
                    $order->coupons_amount = $coupon->amount;
                }
                $order->save(); //保存
            }

			$coin = CommonFun::getArrayValue($order,'order_coin',0);
			$amount = $order['order_amount'];

			//如果有优惠券 先抵扣  下面余额部分不会超
            if($coupon){
                $amount = CommonFun::doNumber($amount, $order['coupons_amount'],'-');
            }

			if($coin > 0){
				if($is_use_coin){//用币则累计进去
					$totalCoin = CommonFun::doNumber($totalCoin,$coin,'+');
				}else{//不用则转换成金钱
					$amount = CommonFun::doNumber($amount,($coin*1),'+');
					$coin = 0;
				}
			}
			
			$pay_balance_amount = 0;
			$order_org_amount = $amount;//订单原始金额 
			if($is_use_balance == 1 && $userBalance > 0){//计算余额能抵扣多少
				if($userBalance >= $amount ){//如果余额大于当前需要金额 则余额全部抵扣
					$pay_balance_amount = $amount;
					$userBalance = CommonFun::doNumber($userBalance,$amount,'-');
					$amount = 0;
				}else{//如果余额小于当前 则全部抵扣
					$pay_balance_amount = $userBalance;
					$amount = CommonFun::doNumber($amount,$userBalance,'-');
					$userBalance = 0;
				}
			}

			$payData [] = [
				'member_id' => $this->member_id,
				'order_id' => $item['id'],
				'order_sn' => $order['order_sn'],
                'pay_type' => $order['pay_type'], //2018.8.8 添加
				'trade_number' => $trade_number,
				'pay_coin' => $coin,//需要根据是否愿意使用
				'pay_balance_amount' => $pay_balance_amount,//余额需要支付多少
				'pay_amount' => $amount,//如果不想用省币 此项目加上订单省币转换的RMB
				'ctime' => date('Y-m-d H:i:s'),
				'utime' => date('Y-m-d H:i:s'),
				
			];
            $ids[] = $item['id'];
			
			$orderArr[] = ['id' => $item ['id'],'order_sn' => $order['order_sn'],'order_org_amount' => $order_org_amount,'pay_coin' => $coin,'pay_balance_amount' => $pay_balance_amount,'pay_amount' => $amount];
			
			$totalBalanceAmount = CommonFun::doNumber($totalBalanceAmount,$pay_balance_amount,'+');
			$totalMoney = CommonFun::doNumber($totalMoney,$amount,'+');
			
		}

        //微信小程序需要记录提交的formid
        if(in_array($pay_type, [OrderEnum::PAY_TYPE_WXMINI, OrderEnum::PAY_TYPE_ALIMINI])){
            $formid = $this->request->post('formid', '');
            if($formid){
                $mini = new MiniTemplateinfo();
                $mini->category = $pay_type==OrderEnum::PAY_TYPE_ALIMINI?2:1;
                $mini->type = MiniTemplateinfo::TYPE_ORDER;
                $mini->obj_id = join(',', $ids);
                $mini->member_id = $this->member_id;
                $mini->form_id = $formid;
                $mini->ctime = time();
                $mini->is_send = 0;
                $mini->send_time = 0;
                $mini->openid = $this->openid;
                $mini->save();
            }
        }

		if($totalCoin > 0 && $info['coin'] < $totalCoin){
			return CommonFun::returnFalse("您的省币：{$info['coin']}不足以支付当前订单需要的省币：{$totalCoin}");
		}
		if($totalBalanceAmount > 0 && $totalBalanceAmount > $info['balance']){
			return CommonFun::returnFalse("您的余额：{$info['balance']}不足以支付当前订单需要的余额：{$totalBalanceAmount}");
		}
		
		$totalWXPayMoney = $totalMoney;		
		if($totalWXPayMoney == 0){//不需要额外三方支付 则直接扣除余额+省币
			if($totalCoin == 0 && $totalBalanceAmount == 0 ){
				return CommonFun::returnFalse("支付失败，需扣除金额：{$totalMoney}，省币：{$totalCoin}，请稍候再试。");
			}
			foreach ($orderArr as $item){
				$res = MembersFinances::usePayment($this->member_id,$item['pay_balance_amount'],$item['pay_coin'],$item ['id']);
				if($res !== true){
					return CommonFun::returnFalse("支付存在失败：{$res}");
				}
				$where ['id'] = $item ['id'];
				$payInfo = [
					'pay_coin' => $item['pay_coin'],
					'pay_amount' => $item['order_org_amount'],
					
					'pay_balance' => $item['pay_balance_amount'],
					'pay_third_amount' => 0,//第三方支付为0 			
				];
				Orders::operation($where,6,'用户',$payInfo);
			}
			return CommonFun::returnSuccess(['is_pay' => 0, 'is_wx_pay' => 0]); //is_wx_pay 将在后续版本中作废
		}

        /**
         * 2018年8月8日 新增  根据支付类型返回需要的第三方支付包
         * $pay_type 使用这个来验证
         */

        $payRes = [];
        switch ($pay_type){
            case OrderEnum::PAY_TYPE_WXMINI: //微信小程序
                $payRes = WxMiniService::tradePub('支付'.Yii::$app->params['basic']['shop_name'].'订单',$totalWXPayMoney,$trade_number,'JSAPI','',$this->openid);
                break;
            case OrderEnum::PAY_TYPE_ALIPAY: //支付宝
                $user_id = AlipayFans::getUserId($this->member_id);
                $payRes = AlipayService::tradePub('支付'.Yii::$app->params['basic']['shop_name'].'订单', $totalWXPayMoney, $trade_number, $user_id);
                break;
            case OrderEnum::PAY_TYPE_ALIMINI: //支付宝小程序
                //获取用户UID
                $user_id = AlipayFans::getUserId($this->member_id);
                $payRes = AliMiniService::tradePub('支付'.Yii::$app->params['basic']['shop_name'].'订单', $totalWXPayMoney, $trade_number, $user_id);
                break;
            case OrderEnum::PAY_TYPE_WEIXIN: //默认为微信
            default:
                $payRes = WxPayService::tradePub('支付'.Yii::$app->params['basic']['shop_name'].'订单',$totalWXPayMoney,$trade_number,'JSAPI','',$this->openid);
                break;
        }

		if($payRes === false){
			return CommonFun::returnFalse("支付中心返回失败，请稍候再试。");
		}

		//写入支付记录
		$fields = ['member_id', 'order_id','order_sn', 'pay_type', 'trade_number','pay_coin','pay_balance_amount','pay_amount','ctime','utime'];
		$res = \Yii::$app->db->createCommand()->batchInsert(OrdersPay::tableName(), $fields, $payData)->execute();
		if($res == 0){
			return CommonFun::returnFalse("写入数据失败，请稍候再试。");
		}

		return CommonFun::returnSuccess([
		    'is_pay' => 1,
            'is_wx_pay' => 1,
            'wx_package' => $payRes, //后续版本作废
            'package' => $payRes,
            'total_money' => $totalWXPayMoney
        ]);
	}
	
	/**
	 * 操作订单
	 * @param number $orderId
	 */
	public function actionOperation(){
		$orderId = intval($this->request->post('id',0));
		$type = intval($this->request->post('type',0));
		$where = [
			'member_id' => $this->member_id,
			'id' => $orderId,
		];
		
		$res = Orders::operation($where,$type);
		if($res !== true){
			return CommonFun::returnFalse('操作失败：'.$res);
		}
		
		return CommonFun::returnSuccess();
	}

	public function actionCommentsList($product_id = 0,$object_id = 0,$type = 0){
		
		$where = [
			'status' => CommonModel::STATUS_ACTIVE,
		];
		if(!empty($product_id)){
			$where['product_id'] = $product_id;
		}
		if(!empty($object_id)){
			$where['object_id'] = $object_id;
		}
		
		if(!empty($type)){
			$where['order_type'] = $type;
		}
	
		$query = OrdersComments::find();
		$query->where($where);
		$query->orderBy('ctime desc');
		$total = $query->count();

		
		$query->with(['member' => function($query){
			$query->select = ['nickname','id'];
		}]);
		
		
		$query->limit($this->pageSize)->offset($this->offset);
		$data = $query->asArray()->all();	 
		return CommonFun::returnSuccess(['total' => $total,'list' => $data]);
	}
	
	/**
	 * 提交评价
	 * @author RTS 2018年4月21日 13:43:46
	 */
	public function actionCommentsAdd(){
		$id = intval($this->request->post('id',0));
		$content = $this->request->post('content','');
		$score = intval($this->request->post('score',5));
		$info = Orders::find()->where(['id' => $id,'member_id' => $this->member_id])->select('title,product_id,order_obj_id,type')->one();
		if(empty($info)){
			return CommonFun::returnFalse('未找到对应订单数据，请稍候再试');
		}
		
		$model = new OrdersComments();
		$model->member_id = $this->member_id;
		$model->product_id = $info['product_id'];
		$model->order_type = $info['type'];
		$model->title = $info['title'];
		$model->content =$content;
		$model->score = $score;
		$model->object_id = $info['order_obj_id'];
		$model->order_id = $id;
		$res = $model->save();
		if($res){
			return CommonFun::returnSuccess();
		}
		return CommonFun::returnFalse('操作失败，请稍候再试。');
	}
	
	/**
	 * 查询订单支付情况
	 * @author RTS 2018年5月2日 09:40:29
	 */
	public function actionQueryPayInfo($ids = ''){
		if(empty($ids)){
			return CommonFun::returnFalse('订单ID为空');
		}
		$ids = explode(',', $ids);
		$return = [];
		$total_pay_coin = 0;//所有支付币
		$total_pay_balance = 0;//所有的余额支付
		$total_pay_amount = 0;//所有的第三方支付金额
		$total_coin = 0;//获得所有的币
        $total_coupon_amount = 0; //优惠券金额
        $order_amount = 0;//订单总金额
        $order_coin = 0;//订单总省币
        $order_shipping_fee = 0;//订单运费
		$point = null; //兑换点信息
		foreach ($ids as $item){
			if(empty($item)){
				continue;
			}
			$info = Orders::find()->where(['id' => $item,'status' => CommonModel::STATUS_ACTIVE,'member_id' => $this->member_id])->asArray()->one();
			if(empty($info)){
				return CommonFun::returnFalse('订单id:'.$item.'获取数据失败，请稍候再试。');
			}
//            $info = Orders::find()->where(['id' => $item,'status' => CommonModel::STATUS_ACTIVE])->asArray()->one();
			if($info['type'] == OrderEnum::TYPE_ONLINEOFF_PAY){ //结算订单只有一单，返回当前兑换点
			    $tmp_point = ExchangePoint::findOne(['merchants_id'=>$info['order_obj_id']]);
			    if($tmp_point){
			        $point = $tmp_point->toArray();
                }
            }
			if($info['payment_status'] != OrderEnum::PAY_STATUS_PAYED){
				return CommonFun::returnFalse('订单id:'.$item.'未支付，请稍候再试。');
			}
			$payInfo = OrdersPay::findOne(['order_id' => $item,'pay_status' => 1]);
			if(!empty($payInfo)){
				$info['pay_coin'] = $payInfo['pay_coin'];
				$info['pay_balance_amount'] = $payInfo['pay_balance_amount'];
				$info['pay_amount'] = $payInfo['pay_amount'];
			}else{
				$info['pay_balance_amount'] = $info['pay_amount'];
				$info['pay_amount'] = 0;//没有第三方支付 则第三方支付为0
			}
			
			$return['details'][$item] = [
				'pay_coin' => $info['pay_coin'],
				'pay_amount' => $info['pay_amount'],
				'pay_balance' => $info['pay_balance_amount'],
				'coin' => $info['member_coin'],
                'coupons_amount' => $info['coupons_amount'],
                'order_amount' => $info['order_amount'],
                'order_coin' => $info['order_coin'],
                'shipping_fee' => $info['shipping_fee']
			];
			$total_pay_coin = CommonFun::doNumber($total_pay_coin,$info['pay_coin'],'+');
			$total_pay_balance = CommonFun::doNumber($total_pay_balance,$info['pay_balance_amount'],'+');
			$total_pay_amount = CommonFun::doNumber($total_pay_amount,$info['pay_amount'],'+');
			$total_coin = CommonFun::doNumber($total_coin,$info['member_coin'],'+');
            $total_coupon_amount = CommonFun::doNumber($total_coupon_amount, $info['coupons_amount'],'+');
            $order_amount = CommonFun::doNumber($order_amount, $info['order_amount'],'+');
            $order_coin = CommonFun::doNumber($order_coin, $info['order_coin'],'+');
            $order_shipping_fee = CommonFun::doNumber($order_shipping_fee, $info['shipping_fee'],'+');
		}
		
		$return['total']['total_pay_coin'] = $total_pay_coin;
		$return['total']['total_pay_balance'] = $total_pay_balance;
		$return['total']['total_pay_amount'] = $total_pay_amount;
		$return['total']['total_coin'] = $total_coin;
		$return['total']['total_coupon_amount'] = $total_coupon_amount;
        $return['total']['order_amount'] = $order_amount;
        $return['total']['order_coin'] = $order_coin;
        $return['total']['order_shipping_fee'] = $order_shipping_fee;
        $return['point'] = $point;
		return CommonFun::returnSuccess($return);
	}
	
	
	
}
