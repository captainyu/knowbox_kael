<?php

namespace usercenter\modules\auth\models;
use common\libs\AppFunc;
use common\libs\Qiniu;
use common\models\Department;
use common\models\Platform;
use common\models\RelateUserPlatform;
use common\models\CommonUser;
use common\models\UserCenter;
use common\models\WorkLevel;
use common\models\WorkType;
use dosamigos\qrcode\QrCode;
use usercenter\components\exception\Exception;
use usercenter\models\RequestBaseModel;
use Yii;
use yii\helpers\FileHelper;

class Api extends RequestBaseModel {


    public $where = [];
    public $where2 = [];
    public $page;
    public $pagesize;


    public $url;
    public $dirname;
    public $logo = false;
    public $matrix_point_size = 10;
    public $margin = 1;

    const SCENARIO_WHERE = "SCENARIO_WHERE";
    const SCENARIO_WHERE_PAGE = "SCENARIO_WHERE_PAGE";
    const SCENARIO_QR = "SCENARIO_QR";
    public $user_id;
    public $sms_content;
    public $check_token;

    public $mobile;


    const SCENARIO_SENDSMS = "SCENARIO_SENDSMS";
    const SCENARIO_SENDSMSTTOMOBILE = "SCENARIO_SENDSMSTTOMOBILE";

    public $platform_id = 0;


    public function scenarios()
    {
        $scenarios =  parent::scenarios();
        $scenarios[self::SCENARIO_WHERE] = ['token','where','where2','platform_id'];
        $scenarios[self::SCENARIO_WHERE_PAGE] = ['token','where','page','pagesize','where2','platform_id'];

        $scenarios[self::SCENARIO_QR] = ['token','url','dirname','logo','matrix_point_size','margin'];

        $scenarios[self::SCENARIO_SENDSMS] = ['user_id','sms_content','check_token'];
        $scenarios[self::SCENARIO_SENDSMSTTOMOBILE] = ['mobile','sms_content','check_token'];

        return $scenarios;
    }

    public function getPlatformId(){
        if(!empty($this->platform_id)){
            return $this->platform_id;
        }
        $sourceUrl = Yii::$app->request->referrer;
        if(empty($sourceUrl)){
            throw new Exception('权限不足，请联系系统管理员',Exception::ERROR_COMMON);
        }
        $sourceUrlArr = parse_url($sourceUrl);
        $host = $sourceUrlArr['host'];
        if(empty($host)){
            throw new Exception('权限不足，请联系系统管理员',Exception::ERROR_COMMON);
        }
        $platformInfo = Platform::findOneByHost($host,$this->auth_platform_id);
        if(empty($platformInfo)){
            throw new Exception('权限不足，请联系管理员',Exception::ERROR_COMMON);
        }
        $this->platform_id = $platformInfo['platform_id'];
        return $this->platform_id;
    }


    public function rules()
    {
        return array_merge([
            [['where','where2'], 'safe'],
            [['sms_content','check_token','mobile'], 'string'],
            [['page','pagesize','platform_id','user_id'], 'integer'],
            [['where'], 'required','on'=>self::SCENARIO_WHERE],
            [['page','pagesize'],'required','on'=>self::SCENARIO_WHERE_PAGE],

            [['url','dirname'],'required','on'=>self::SCENARIO_QR],

            [['user_id','sms_content'],'required','on'=>self::SCENARIO_SENDSMS],
            [['mobile','sms_content'],'required','on'=>self::SCENARIO_SENDSMSTTOMOBILE],

        ],parent::rules());
    }

    public function sendSmsToMobile(){
        if(empty($this->check_token) || strlen($this->check_token) != 42){
            throw new Exception("参数校验失败");
        }
        $checkMd5 = substr($this->check_token,0,32);
        $timestamp = substr($this->check_token,32);
        if(abs(time() - $timestamp) > 60){
            //60s延迟
            throw new Exception("参数校验失败");
        }
        if($checkMd5 != md5($this->mobile.'|'.$this->sms_content.'|'.date("Ymd",$timestamp) .'|'.$timestamp . '|knowbox')){
            throw new Exception("参数校验失败");
        }
        $res = AppFunc::smsSend($this->mobile, $this->sms_content);
        return $res;
    }


    public function sendSms(){
        if($this->check_token != md5($this->user_id.'|'.$this->sms_content.'|'.date("Ymd") . '|knowbox')){
            throw new Exception("参数校验失败");
        }
        $user = CommonUser::find()->where(['id'=>$this->user_id])->asArray(true)->one();
        if (empty($user)) {
            throw new Exception("发送用户不存在", Exception::ERROR_COMMON);
        }
        $res = AppFunc::smsSend($user['mobile'], $this->sms_content);
        return $res;
    }


    public function getUserListByPlatformWhere(){
        $userList = UserCenter::find()
            ->where($this->where)
            ->andWhere($this->where2)
            ->andWhere(['status'=>UserCenter::STATUS_VALID])
            ->asArray(true)->all();
        $userIds = array_column($userList,'id');
        $relate = RelateUserPlatform::findListByUserPlatform($userIds,$this->getPlatformId());
        $userIds = array_column($relate,'user_id');
        $userListFitler = [];
        $workTypeEntity = WorkType::findAllList();
        $workLevelEntity = WorkLevel::findAllList();
        $departmentEntity = Department::findAllList();
        foreach($userList as $v){
            if(in_array($v['id'],$userIds)){
                $v['work_type_name'] = isset($workTypeEntity[$v['work_type']]) ? $workTypeEntity[$v['work_type']]['name'] : "未知";
                $v['work_level_name'] = isset($workLevelEntity[$v['work_level']]) ? $workLevelEntity[$v['work_level']]['name'] : "未知";
                $v['department_name'] = isset($departmentEntity[$v['department_id']]) ? $departmentEntity[$v['department_id']]['department_name'] : "未知";
                $v['password'] = "";
                $userListFitler[] = $v;
            }
        }

        return $userListFitler;
    }

    public function getUserListByWhere(){
        $userList = UserCenter::find()
            ->where($this->where)
            ->andWhere($this->where2)
            ->andWhere(['status'=>UserCenter::STATUS_VALID])
            ->asArray(true)->all();
        $workTypeEntity = WorkType::findAllList();
        $workLevelEntity = WorkLevel::findAllList();
        $departmentEntity = Department::findAllList();
        foreach($userList as $k=>$v){
            $v['work_type_name'] = isset($workTypeEntity[$v['work_type']]) ? $workTypeEntity[$v['work_type']]['name'] : "未知";
            $v['work_level_name'] = isset($workLevelEntity[$v['work_level']]) ? $workLevelEntity[$v['work_level']]['name'] : "未知";
            $v['department_name'] = isset($departmentEntity[$v['department_id']]) ? $departmentEntity[$v['department_id']]['department_name'] : "未知";
            $v['password'] = "";
            $userList[$k] = $v;
        }
        return $userList;
    }

    public function getUserListPageByPlatformWhere(){

        $this->pagesize = max($this->pagesize,1);
        $this->page = max($this->page,1);

        if(empty($this->where) && empty($this->where2)){
            $relateList = RelateUserPlatform::findListByPlatformPage($this->getPlatformId(),$this->page,$this->pagesize);
            $count = RelateUserPlatform::findCoutByPlatfrom($this->getPlatformId());
            $userIds = array_column($relateList,'user_id');
            $this->where = ['id'=>$userIds];
            $list = $this->getUserListByWhere();
        }else{
            $userList = $this->getUserListByPlatformWhere();
            $list = array_slice($userList,($this->page - 1)*$this->pagesize,$this->pagesize);
            $count = count($userList);
        }

        return [
            'list'=>$list,
            'total'=>$count,
        ];
    }


    public function getImage(){
        $qrcodePath = \Yii::getAlias('@runtime/qrcode');
        if (!is_dir($qrcodePath)) {
            FileHelper::createDirectory($qrcodePath, '0755', true);
        }
        $qrname = strval(microtime(true)).'.png';
        $qrpath = $qrcodePath.DIRECTORY_SEPARATOR.$qrname;
        //生成二维码图片
        QrCode::png($this->url, $qrpath, "H", $this->matrix_point_size, $this->margin);

        if ($this->logo !== false) {
            $QR = imagecreatefromstring(file_get_contents($qrpath));
            $logo = imagecreatefromstring(file_get_contents($this->logo));
            $QR_width = imagesx($QR);//二维码图片宽度
            $QR_height = imagesy($QR);//二维码图片高度
            $logo_width = imagesx($logo);//logo图片宽度
            $logo_height = imagesy($logo);//logo图片高度
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width/$logo_qr_width;
            $logo_qr_height = $logo_height/$scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //重新组合图片并调整大小
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,
                $logo_qr_height, $logo_width, $logo_height);
            imagepng($QR,$qrpath);
        }
        return Qiniu::uploadFile($qrpath,$this->dirname.'/'.$qrname);
    }
}