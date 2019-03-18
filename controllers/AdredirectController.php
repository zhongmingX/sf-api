<?php
namespace api\controllers;

use common\components\CommonFun;
use common\models\AdRecord;
use common\models\Ad;

class AdredirectController extends BaseLightController {


    /**
     * 广告跳转
     * @param number $adId
     */
    public function actionIndex(){
    	$adId = CommonFun::getParams('aid',0);
    	$adRecord = new AdRecord();
    	$ad = Ad::findOne($adId);
    	
    	if($ad){
    		$adRecord->ip = CommonFun::getClientIP();
    		$adRecord->member_id = $this->member_id;
    		$adRecord->ad_id = $adId;
    		$adRecord->ctime = time();
    		$ad->click_count += 1;
    		$ad->save();
    		$adRecord->save();
    		return $this->redirect($ad->url);
    	}
    }
}
