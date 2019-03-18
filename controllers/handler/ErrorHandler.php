<?php
/**
 * Created by PhpStorm.
 * User: zhongming
 * Date: 2018/2/4 上午10:27
 */

namespace api\controllers\handler;
use Yii;
use yii\base\ErrorHandler as BaseErrorHandler;

class ErrorHandler extends BaseErrorHandler {

    /**
     * Renders the exception.
     * @param \Exception $exception the exception to be rendered.
     */
    protected function renderException($exception) {
    	
        $response = Yii::$app->response;
        $response->statusText = $exception->getMessage();

        if($exception->getCode() == 0){
            $response->statusCode = 404;
            Yii::$app->end(0, $response);
        }else{
            $response->statusCode = 500;
            Yii::$app->end(0, $response);
        }
    }
}