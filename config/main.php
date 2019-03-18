<?php

$params = array_merge(
        require(__DIR__ . '/../../common/config/params.php'), require(__DIR__ . '/params.php')
);

return [
    'id' => 'shenglife-api',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'api\controllers',
    'defaultRoute' => 'default',
    'bootstrap' => ['log'],
    'modules' => [
        'local' => [ //本地
            'class' => 'app\modules\local\Index',
        ],
        'exchange' => [ //兑换
            'class' => 'app\modules\exchange\Index',
        ],
        'mall' => [ //商城
        	'class' => 'app\modules\mall\Index',
        ],
        'member' => [ //个人中心
            'class' => 'app\modules\member\Index',
        ],
        'cart' => [ //购物车
       		'class' => 'app\modules\cart\Index',
        ],
        'order' => [//购物车
        	'class' => 'app\modules\order\Index',
        ],
        'notify' => [//通知模块
        	'class' => 'app\modules\notify\Index',
        ],
        'merchants' => [ //商家中心
        	'class' => 'app\modules\merchants\Index',
        ],
        'kd'=>[
        	'class'=> 'app\modules\kd\Index',
        ],
        'article'=>[
        	'class'=> 'app\modules\article\Index',
        ],
        'activity' => [
            'class' => 'app\modules\activity\index',
        ],
    ],
    'components' => [
        'user' => [
            'identityClass' => false,
            'enableAutoLogin' => false,
            'loginUrl' => null
        ],
        'request' => [
            'csrfParam' => 'oskc_92~0',
            'enableCsrfValidation' => false,
            'cookieValidationKey' => 'EJBy8WRohzpqJY7BTurjQaft2NV-g1cA',
            'enableCookieValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ]
        ],


//        'response' => [
//            'class' => 'yii\web\Response',
//            'on beforeSend' => function ($event) {
//                $response = $event->sender;
//                $response->data = [
//                    'code' => $response->getStatusCode(),
//                    'data' => $response->data,
//                    'message' => $response->statusText
//                ];
//                $response->format = yii\web\Response::FORMAT_JSON;
//            },
//        ],
//
        'urlManager' => [
            /*
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            */
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'suffix'=>'.html',
            /*
            'rules' => [
                ['class' => 'yii\rest\UrlRule', 'controller' => 'common'],
                ['class' => 'yii\rest\UrlRule', 'controller' => 'merchants'],
                ['class' => 'yii\rest\UrlRule', 'controller' => 'default']
            ],
            */
        ],
        'errorHandler' => [
            //'class' => 'api\controllers\handler\ErrorHandler'
        ],

    ],
    'params' => $params,
];
