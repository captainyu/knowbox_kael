<?php
namespace usercenter\modules\admin\controllers;

use usercenter\controllers\BaseController;
use usercenter\modules\admin\models\KaelPlatform;
use Yii;

class PlatformController extends BaseController{

    public $enableCsrfValidation = false;

    public function actionIndex()
    {
        try{
            Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;
            return $this->renderPartial('index');
        }catch(\Exception $e){
            $this->error($e);
        }
    }


    public function actionList(){
        try{
            $model = new KaelPlatform(['scenario'=>KaelPlatform::SCENARIO_LIST]);
            $model->load($this->loadData);
            $model->validate();
            $data = $model->pagelist();
            return $this->success($data);
        }catch(\Exception $e){
            return $this->error($e);
        }
    }

    public function actionDel(){
        try{
            $model = new KaelPlatform(['scenario'=>KaelPlatform::SCENARIO_DEL]);
            $model->load($this->loadData);
            $model->validate();
            $data = $model->del();
            return $this->success($data);
        }catch(\Exception $e){
            return $this->error($e);
        }
    }


    public function actionEdit(){
        try{
            $model = new KaelPlatform(['scenario'=>KaelPlatform::SCENARIO_EDIT]);
            $model->load($this->loadData);
            $model->validate();
            $model->edit();
            return $this->success();
        }catch(\Exception $e){
            return $this->error($e);
        }
    }

    public function actionAdd(){
        try{
            $model = new KaelPlatform(['scenario'=>KaelPlatform::SCENARIO_EDIT]);
            $model->load($this->loadData);
            $model->validate();
            $model->add();
            return $this->success();
        }catch(\Exception $e){
            return $this->error($e);
        }
    }

}