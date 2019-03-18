<?php
namespace app\modules\member\controllers;
/**
 * Created by PhpStorm.
 * User: zhongming
 * Date: 2018/4/3 下午1:42
 */
use common\components\CommonValidate;
use common\models\MembersBanks;
use common\models\MerchantsFreezes;
use gmars\sms\Sms;
use \Yii;
use api\controllers\BaseController;
use common\components\CommonFun;
use common\models\Members;
use common\models\Alisms;
use common\models\MembersFavorite;
use yii\db\Expression;
use api\controllers\MemberBaseController;

class FavoriteController extends MemberBaseController{

    //用户收藏添加
    public function actionCreate(){
        if($this->isPost){
            $type = Yii::$app->request->post('type');
            $obj_id = Yii::$app->request->post('obj_id');
            $title = trim(Yii::$app->request->post('title'));

            if(!in_array($type, MembersFavorite::$TYPE_KEYS)){
                CommonFun::returnFalse('type error');
            }

            if($obj_id < 3 || CommonFun::utf8_strlen($title) < 2){
                CommonFun::returnFalse('obj_id/title format error');
            }

            //查询是否已经收藏
            $m = MembersFavorite::findOne(['member_id'=>$this->member_id, 'obj_id'=>$obj_id, 'type'=>$type]);
            if($m){
                return CommonFun::returnFalse($title.' have favorite');
            }
            $m = new MembersFavorite();
            $m->member_id = $this->member_id;
            $m->type = $type;
            $m->obj_id = $obj_id;
            $m->title = $title;
            $m->ctime = time();
            $m->status = 1;
            $m->save();
            if($m->save()){
                CommonFun::returnSuccess();
            }
        }
        CommonFun::returnFalse('favorite create fail');
    }

    //获取收藏LIST
    public function actionLists($type){
        if(!in_array($type, MembersFavorite::$TYPE_KEYS)){
            CommonFun::returnFalse('type error');
        }

        $query = $count = MembersFavorite::find()
            ->select(new Expression("id, type, obj_id, title, from_unixtime(ctime, '%Y-%m-%d') ctime"))
            ->where('member_id=:mid and type=:type and status = 1', [':mid'=>$this->member_id, ':type'=>$type]);

        $total = $count->count();
        $lists = $query->orderBy('ctime desc')->offset($this->offset)->limit($this->pageSize)->asArray()->all();
        return CommonFun::returnSuccess(['total' => $total, 'page_size'=>$this->pageSize,'page_num' => ++$this->pageNum, 'lists' => $lists]);
    }

    /**
     * 删除单条收藏内容
     * @throws \Exception
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete(){
        if($this->isPost){
            $id = Yii::$app->request->post('id');
            $m = MembersFavorite::findOne(['id'=>$id, 'member_id'=>$this->member_id]);
            if($m && $m->delete()){
                CommonFun::returnSuccess();
            }
        }
        return CommonFun::returnFalse('favorite delete fail');
    }

    //获取总数
    public function actionTotal($type = false){
        $m = MembersFavorite::find()->where("member_id=:mid", [':mid'=>$this->member_id]);
        if($type && in_array($type, MembersFavorite::$TYPE_KEYS)){
            $m->andWhere(['=', 'type', $type]);
        }
        return CommonFun::returnSuccess(['total'=>$m->count()]);
    }
    
    
    /**
     * 查询收藏状
     * @author RTS 2018年4月15日 10:39:57
     */
    public function actionQuery(){
    	$type = CommonFun::getParams('type',1);
    	$obj_id = CommonFun::getParams('obj_id',1);
    	$res = MembersFavorite::findOne(['type' => $type,'obj_id' => $obj_id,'status' => 1, 'member_id'=>$this->member_id]);
    	$is_favorite = !empty($res);
    	return CommonFun::returnSuccess(['is_favorite' => intval($is_favorite)]);
    }


}