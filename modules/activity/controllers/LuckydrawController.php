<?php

namespace app\modules\activity\controllers;

use common\components\CommonFun;
Use api\controllers\BaseController;
use common\models\LuckydrawProducts;
use common\models\MembersCurrency;
use common\models\MembersFinances;
use common\models\MembersLuckydrawAddress;
use common\models\MembersLuckydraws;
use common\models\MembersAddress;
use common\models\MiniTemplateinfo;
use api\controllers\MemberBaseController;

/**
 * Default controller for the `activity` module
 */
class LuckydrawController extends MemberBaseController
{
    /**
     * Renders the index view for the module
     * @return string
     *
     * $type 1当期 2下期 3往期
     */
    public function actionIndex($type = 1)
    {
        $orderby = CommonFun::getParams('order', 'coin desc');
        if(strripos($orderby, ' desc') === false && strripos($orderby, ' asc') === false){
            $orderby = $orderby . ' desc';
        }

        //在当前时间范围内属本期  大于当前时间属下期
        $query = LuckydrawProducts::find()
            ->select('id,product_name,product_desc,product_images,coin,min_people,max_people,start_ctime,end_ctime,status')
            ->where('active=1');
        switch ($type){
            case 1:
//                $query->andWhere(['<=', 'start_ctime', date('Y-m-d H:i:s', time())]);
//                $query->andWhere(['>=', 'end_ctime', date('Y-m-d H:i:s', time())]);
                $query->andWhere(['in', 'status', [1,2]]);
                break;
            case 2:
//                $query->andWhere(['>', 'start_ctime', date('Y-m-d H:i:s', time())]);
                $query->andWhere(['=', 'status', 0]);
                break;
            default:
//                $query->andWhere(['<', 'end_ctime', date('Y-m-d H:i:s', time())]);
                $query->andWhere(['in', 'status', [8,9]]);
                break;
        }

        $model = $query->orderBy($orderby)
            ->asArray()
            ->all();

        $data = [];
        if($model){
            foreach($model as $k=>$v){
                $data[$k] = $v;
                //查询当前抽奖人数
                $data[$k]['people_num'] =  LuckydrawProducts::getNumber($v['id']);
                $data[$k]['start_ctime'] = date('Y-m-d H:i', strtotime($v['start_ctime']));
                $data[$k]['end_ctime'] = date('Y-m-d H:i', strtotime($v['end_ctime']));
                $data[$k]['winner'] = '';
                if($v['status'] == 9){ //正常结束，找出中奖人
                    $data[$k]['winner'] = MembersLuckydraws::getWinner($v['id']);
                }
            }
        }
        CommonFun::returnSuccess($data);
    }

    //商品详情
    public function actionView($id){
        $model = LuckydrawProducts::findOne($id);
        if(!$model){
            return CommonFun::returnFalse('数据错误');
        }

        $data = $model->attributes;

        //
        $member = MembersLuckydraws::find()
            ->where('member_id=:mid and product_id=:pid', [':pid'=>$id, ':mid'=>$this->member_id])
            ->one();

        unset($data['version']);
        unset($data['ctime']);
        unset($data['active']);
        $data['member'] = '';
        if($member){
            $data['member'] = $member->attributes;
        }
        return CommonFun::returnSuccess($data);
    }

    //抽奖参与列表
    public function actionGetLists($id){
        $data = [];
        $model = MembersLuckydraws::find()
            ->where('product_id=:pid',[':pid'=>$id])
            ->orderBy('ctime desc')
            ->all();

        if($model){
            foreach($model as $k=>$v){
                $data[$k]['uid'] = substr_replace($v['member_id'], '****', 2, 3);;
                $data[$k]['datestr'] = date("Y-m-d H:i:s", $v['ctime']);
                $data[$k]['statusText'] = MembersLuckydraws::$STATUS[$v['status']];
            }
        }
        return CommonFun::returnSuccess($data);
    }

    //开始抽奖
    public function actionDo(){
        if($this->isPost){
            $pid = \Yii::$app->request->post('pid');
            $model = LuckydrawProducts::findOne($pid);
            $res = [];
            if(!$model){
                return CommonFun::returnFalse('数据错误');
            }

            //判断活动有没有开启
            if($model->status == LuckydrawProducts::STATUS_NOSTART){
                $res['status'] = 0;
                $res['msg'] = '该活动还未开始';
            }else if($model->status == LuckydrawProducts::STATUS_OPEN){
                $res['status'] = 0;
                $res['msg'] = '抽奖中';
            }else if($model->status == LuckydrawProducts::STATUS_END){
                $res['status'] = 0;
                $res['msg'] = '活动已经结束';
            }else if($model->status == LuckydrawProducts::STATUS_NOTNUMBER){
                $res['status'] = 0;
                $res['msg'] = '抽奖人数不足, 结束';
            }else if($model->status == LuckydrawProducts::STATUS_START){
                if($model->max_people > 0){
                    $num = LuckydrawProducts::getNumber($pid);
                    if($num >= $model->max_people){
                        $res['status'] = 0;
                        $res['msg'] = '已经达到参与上限';
                        return CommonFun::returnSuccess($res);
                    }
                }

                $data = MembersLuckydraws::find()
                    ->where('member_id=:mid and product_id=:pid', [':pid'=>$pid, ':mid'=>$this->member_id])
                    ->one();
                if($data){
                    $res['status'] = 0;
                    $res['msg'] = '该活动已经参与';
                }else{
                    //判断省币是否够
                    $finance = MembersFinances::findOne(['member_id'=>$this->member_id]);
                    $coin = CommonFun::doNumber($finance->coin);
                    if($coin < $model->coin){
                        return CommonFun::returnFalse('省币不足');
                    }

                    //冻结省币
                    MembersFinances::coinFreeze($this->member_id, $model->coin, MembersCurrency::SOURCE_LUCKDRAW_FREEZE);

                    $data = new MembersLuckydraws();
                    $data->member_id = $this->member_id;
                    $data->product_id = $pid;
                    $data->coin = $model->coin;
                    $data->taxrate = $model->taxrate;
                    $data->ctime = time();
                    $data->status = 1;
                    if($data->save()){
                        $res['status'] = 1;
                        $res['msg'] = '参加抽奖活动成功';
                    }

                    //记录用户提交FORMID
                    if($this->api_source == 'miniprogram'){
                        $formid = \Yii::$app->request->post('formid');
                        if($formid){
                            $mini = new MiniTemplateinfo();
                            $mini->type = MiniTemplateinfo::TYPE_LUCKYDRAW;
                            $mini->obj_id = 'luckydraw_'.$pid;
                            $mini->member_id = $this->member_id;
                            $mini->form_id = $formid;
                            $mini->ctime = time();
                            $mini->is_send = 0;
                            $mini->send_time = 0;
                            $mini->openid = $this->openid;
                            $mini->save();
                        }
                    }
                }
            }else{
                $res['status'] = 0;
                $res['msg'] = '活动状态错误';
            }

            return CommonFun::returnSuccess($res);
        }
    }

    //获取我的抽奖记录
    public function actionRecord(){
        $data = [];
        $model = MembersLuckydraws::find()
            ->where('member_id=:mid', [':mid'=>$this->member_id])
            ->with(['product', 'address'])
            ->orderBy('ctime desc')
            ->asArray()
            ->all();

        if($model){
            foreach ($model as $k=>$v){
                $data[$k]['id'] = $v['id'];
                $data[$k]['product_id'] = $v['product_id'];
                $data[$k]['date'] = date('Y-m-d H:i:s', $v['ctime']);
                $data[$k]['status'] = $v['status'];
                $data[$k]['product_name'] = $v['product']['product_name'];
                $data[$k]['product_image'] = '';
                if($v['product']['product_images']){
                    $tmp = explode(',', $v['product']['product_images']);
                    $data[$k]['product_image'] = $tmp[0];
                }
                $data[$k]['product_coin'] = $v['product']['coin'];
                $data[$k]['address'] = $v['address'];
            }
        }
        return CommonFun::returnSuccess($data);
    }

    //设置收货地址
    public function actionSettingAddress(){
        if($this->isPost) {
            $id = \Yii::$app->request->post('id');
            //取默认收货地址
            $address = MembersAddress::getDefaultAddress($this->member_id);
            if(!$address){
                return CommonFun::returnFalse('未找到默认地址');
            }
            $model = MembersLuckydrawAddress::findOne(['ml_id'=>$id]);
            if(!$model){
                $model = new MembersLuckydrawAddress();
            }

            $model->ml_id = $id;
            $model->member_id = $this->member_id;
            $model->name = $address['name'];
            $model->mobile = $address['mobile'];
            $model->area = $address['area'];
            $model->address = $address['address'];
            $model->status = 1;
            if($model->save()){
                return CommonFun::returnSuccess();
            }
            return CommonFun::returnFalse('系统错误');
        }
    }
}
