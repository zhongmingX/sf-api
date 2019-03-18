<?php

namespace api\controllers;

use Yii;

use yii\web\Controller;


class DocController extends Controller {
	public $enableCsrfValidation = false;
	public function init() {
		
	}
	public function actions() {
		return [
			'index' => [
				'class' => 'light\swagger\RRKDApiAction',
				'path' => Yii::getAlias ( '@yml' )
			]
		];
	}

	
}
