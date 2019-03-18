<?php
namespace api\controllers;

use common\models\OpenCity;
use common\components\CommonFun;
use yii\web\Controller;

class CityController extends BaseController {

    //开通城市
    public function actionOpenCitys(){
        $citys = OpenCity::getCitys();
        $data = [];
        if($citys){
            foreach ($citys as $k=>$item){
                $data[] = [
                	'id'=>$item['id'],
                	'name'=>$item['name'],
                	'code'=>$item['code'],
                    'longitude' => $item['longitude'],
                    'latitude' => $item['latitude']
				];
            }
        }
        
        return CommonFun::returnSuccess($data);
    }
}
