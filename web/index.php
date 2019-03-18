<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'prod');

$libPath = __DIR__.'/../../yii-base-framework';
defined('YII_LIB_ROOT') or define('YII_LIB_ROOT', $libPath);//框架所在目录


require(YII_LIB_ROOT . '/vendor/autoload.php');
require(YII_LIB_ROOT . '/vendor/yiisoft/yii2/Yii.php');


require(__DIR__ . '/../../common/config/bootstrap.php');
require(__DIR__ . '/../config/bootstrap.php');

$config = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../../common/config/main.php'),
    require(__DIR__ . '/../config/main.php')
);

Yii::setAlias('yml', __DIR__ . '/../yml');

if (!YII_ENV_TEST) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'allowedIPs' => ['*']
    ];
}

(new yii\web\Application($config))->run();
