<?php
namespace api\controllers;

use common\extend\OSS\Common;
use common\models\Adp;
use common\components\CommonFun;
use common\models\AdRecord;

class AdpController extends BaseController {

    /**
     * 获取banner位
     * @param string $pos
     */
    public function actionBanner(){
        $pos = CommonFun::getParams('pos', 'local');
        $cid = 17;
        if($pos == 'local'){
            $cid = 18;
        }
        return $this->actionAdps($cid);
    }

    /**
     * 获取广告位广告
     * @param $id
     * @param int $type category|adp
     */
    public function actionAdps($id = '', $type='category'){
        if($type == 'category'){
            $cid = intval($id);
            $adp = Adp::getCategoryAdps($cid, $this->city_id,$this->token,$this->openid);
            return CommonFun::returnSuccess($adp);
        }else{
            $apid = intval($id);
            if($apid > 0){
                $adp = Adp::getAdpLists($apid, $this->city_id,$this->token,$this->openid);
            }else{
                $adp = [];
            }

            return CommonFun::returnSuccess($adp);
        }

    }
    
   
}
