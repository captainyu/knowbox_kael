<?php

namespace usercenter\modules\admin\models;

use common\libs\Constant;
use common\libs\UserToken;
use common\models\CommonModulesUser;
use common\models\Department;
use common\models\DingtalkUser;
use common\models\LogAuthUser;
use common\models\Platform;
use common\models\RelateAdminDepartment;
use common\models\RelateDepartmentPlatform;
use common\models\RelateUserPlatform;
use common\models\Role;
use common\models\UserCenter;
use common\models\WorkLevel;
use common\models\WorkType;
use usercenter\components\exception\Exception;
use usercenter\models\RequestBaseModel;
use Yii;

class User extends RequestBaseModel
{

    const SCENARIO_LIST = "SCENARIO_LIST";
    const SCENARIO_EDIT = "SCENARIO_EDIT";
    const SCENARIO_DEL = "SCENARIO_DEL";
    const SCENARIO_IMPORT = "SCENARIO_IMPORT";
    const SCENARIO_CHECKMOBILE = "SCENARIO_CHECKMOBILE";
    const SCENARIO_USER_UPLOAD = "SCENARIO_USER_UPLOAD";
    const SCENARIO_USER_DOWNLOAD = "SCENARIO_USER_DOWNLOAD";
    const SCENARIO_PLAT_BY_DEPARTMENT = "SCENARIO_PLAT_BY_DEPARTMENT";
    const SCENARIO_EDITPRIV = "SCENARIO_EDITPRIV";
    public $page = 1;
    public $pagesize = 20;
    public $data = [];
    public $id;
    public $type;
    public $mobile;
    public $filter = [];
    public $user_source = "admin";
    public $user_type = "1";
    public $is_admin = "0";
    public $department_id;
    public $work_level;
    public $work_type;
    public $work_number;


    public function rules()
    {
        return array_merge([
            [['page', 'pagesize', 'id','department_id', 'work_level', 'work_type'], 'integer'],
            [['type', 'mobile', 'work_number'], 'string'],
            [['data', 'id'], 'required', 'on' => self::SCENARIO_EDIT],
            [['id'], 'required', 'on' => self::SCENARIO_EDITPRIV],
            [['mobile'], 'required', 'on' => self::SCENARIO_CHECKMOBILE],
            [['id'], 'required', 'on' => self::SCENARIO_DEL],
            [['page', 'pagesize'], 'required', 'on' => self::SCENARIO_LIST],
            [['department_id'],'required','on'=>self::SCENARIO_PLAT_BY_DEPARTMENT],
            [['data', 'filter'], 'safe']
        ], parent::rules());
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        return $scenarios;
    }


    public function checkUserMobile()
    {
        $userInfo = UserCenter::find()->where(['mobile' => $this->mobile, 'status' => UserCenter::STATUS_VALID])->one();
        if(!empty($userInfo)){
            //已存在
            throw new Exception('用户已存在，无法新增',Exception::ERROR_COMMON);
        }
        if (empty($userInfo)) {
            return false;
        }
        return $userInfo;
    }

    public function checkSuperAuth(){
        $platformInfo = Platform::findOneById(1);
        !empty($platformInfo['allow_ips']) && $platformInfo['allow_ips'] = $platformInfo['allow_ips'].','.(Yii::$app->params['allow_ips']);
        $clientIPAllow = explode(',',$platformInfo['allow_ips']);
        $clientIP = UserToken::getRealIP(false);
        if(!empty($platformInfo['allow_ips']) && !in_array($clientIP,$clientIPAllow)){
            throw new Exception('无权访问，请联系管理员',Exception::ERROR_COMMON);
        }
        if(!in_array($this->user['admin'],[Role::ROLE_ADMIN])){
            throw new Exception('权限不足',Exception::ERROR_COMMON);
        }
        return true;
    }


    public function checkUserAuth($userId=-1)
    {
        $platformInfo = Platform::findOneById(1);
        !empty($platformInfo['allow_ips']) && $platformInfo['allow_ips'] = $platformInfo['allow_ips'].','.(Yii::$app->params['allow_ips']);
        $clientIPAllow = explode(',',$platformInfo['allow_ips']);
        $clientIP = UserToken::getRealIP(false);
        if(!empty($platformInfo['allow_ips']) && !in_array($clientIP,$clientIPAllow)){
            throw new Exception('无权访问，请联系管理员',Exception::ERROR_COMMON);
        }
        if(!in_array($this->user['admin'],[Role::ROLE_ADMIN,Role::ROLE_DEPARTMENT_ADMIN])){
            throw new Exception('权限不足',Exception::ERROR_COMMON);
        }
        if($userId != -1){
            if($this->user['admin'] == Role::ROLE_DEPARTMENT_ADMIN){
                $toUserInfo = UserCenter::findOneById($userId);
                $deparmentId = $toUserInfo['department_id'];
                $isAuth = RelateAdminDepartment::findListByAdminDepartment($userId,$deparmentId);
                if(empty($isAuth)){
                    throw new Exception('权限不足',Exception::ERROR_COMMON);
                }
            }
        }
        return true;
    }

    public function roleList()
    {

        $roleList = Role::findAllList();
        return $roleList;
    }

    public function platformList()
    {

        $platList = Platform::findAllList();
        return $platList;
    }

    public function platformListByAdminDepartment($departmentId = -1)
    {
        if($departmentId > 0){
            $this->department_id = $departmentId;
        }
        if($this->user['admin'] == Role::ROLE_ADMIN){
            if($this->department_id == -1){
                $platList = [];
//                $platList = Platform::findAllList();
            }else{
//                $departmentAllowPlat = RelateDepartmentPlatform::findListByDepartment($this->department_id);
//                $platId = array_column($departmentAllowPlat,'platform_id');
//                $platList = Platform::findListById($platId);
                $platList = Platform::findAllList();
            }
        }elseif($this->user['admin'] == Role::ROLE_DEPARTMENT_ADMIN){
            if($this->department_id == -1){
                $platList = [];
            }else{
//                $departmentAllowPlat = RelateDepartmentPlatform::findListByDepartment($this->department_id);
                $departmentAllowPlatSelf = RelateAdminDepartment::findListByAdminDepartment($this->user['id'],$this->department_id);
//                $platIdAllow = array_column($departmentAllowPlat,'platform_id');
                $platIdAllowSelf = array_column($departmentAllowPlatSelf,'platform_id');
//                $platId = array_intersect($platIdAllow,$platIdAllowSelf);
                $platList = Platform::findListById($platIdAllowSelf);
//                $platList = Platform::findAllList();
            }

        }else{
            $platList = [];
        }
        return $platList;
    }

    public function departmentList()
    {

        $departmentList = Department::findAllList();
        return $departmentList;
    }

    public function workTypeList()
    {

        $list = WorkType::findAllList();
        return $list;
    }

    public function workLevelList()
    {

        $list = WorkLevel::findAllList();
        return $list;
    }

    public function departmentListByAdmin()
    {
        if($this->user['admin'] == Role::ROLE_DEPARTMENT_ADMIN){
            $departmentList = RelateAdminDepartment::findListByAdmin($this->user['id']);
            $departmentIds = array_column($departmentList,'department_id');
            $departmentList = Department::findListById($departmentIds);
            return $departmentList;
        }elseif($this->user['admin'] == Role::ROLE_ADMIN){
            $departmentList = Department::findAllList();
            return $departmentList;
        }else{
            return [];
        }
    }

    /**
     * @return array
     * @throws Exception
     * 分页列表
     */
    public function pagelist()
    {

        $this->checkUserAuth();
        $search = !empty($this->filter['search']) ? trim($this->filter['search']) : "";
        $where = [];
        isset($this->filter['work_level']) && $this->filter['work_level'] != -1 && $where['work_level'] = $this->filter['work_level'];
        isset($this->filter['work_type']) && $this->filter['work_type'] != -1 && $where['work_type'] = $this->filter['work_type'];

        isset($this->filter['role']) && $this->filter['role'] != -1 && $where['admin'] = $this->filter['role'];
        isset($this->filter['department']) && $this->filter['department'] != -1 && $where['department_id'] = $this->filter['department'];
        if(isset($this->filter['department']) && $this->filter['department'] == -1){
            if($this->user['admin'] == Role::ROLE_DEPARTMENT_ADMIN){
                $departmentList = RelateAdminDepartment::findListByAdmin($this->user['id']);
                $where['department_id'] = array_column($departmentList,'department_id');
            }
        }
//        isset($this->filter['subject']) && is_numeric($this->filter['subject']) && $where['subject'] = $this->filter['subject'];
//        isset($this->filter['grade_part']) && is_numeric($this->filter['grade_part']) && $where['grade_part'] = $this->filter['grade_part'];
        isset($this->filter['user_type']) && $this->filter['user_type'] != -1 && $where['user_type'] = $this->filter['user_type'];

        $leftjoin = [];
        if(isset($this->filter['platform']) && $this->filter['platform'] != -1){
            $leftjoin[] = [RelateUserPlatform::tableName().' b','a.id = b.user_id'];
            $where['b.platform_id'] = $this->filter['platform'];
        }
        $userList = UserCenter::findUserSearch($this->page,$this->pagesize,$search,$where,$leftjoin);
        $total  = UserCenter::findUserSearchCount($search,$where,$leftjoin);
        //部门 权限
        $roleEntity = Role::findAllList();
        $userIds = array_column($userList,'id');
        $platformList = RelateUserPlatform::findListByUserPlatform($userIds);
        $relateAdminDepart = RelateAdminDepartment::findListByAdmin($userIds);
        $platformListUpdateTime = RelateUserPlatform::findLastUpdateTime($userIds);
        $relateAdminDepartUpdateTime = RelateAdminDepartment::findLastUpdateTime($userIds);
        //实体
        $departmentEntity  = Department::findAllList();
        $platformEntity = Platform::findAllList();
        $workTypeEntity = WorkType::findAllList();
        $workLevelEntity = WorkLevel::findAllList();
        //拼装
        foreach($userList as $k=>$v){
            //updateTime
            isset($platformListUpdateTime[$v['id']])
            && $platformListUpdateTime[$v['id']]['update_time'] > $v['update_time']
            && $v['update_time'] = $platformListUpdateTime[$v['id']]['update_time'];

            isset($relateAdminDepartUpdateTime[$v['id']])
            && $relateAdminDepartUpdateTime[$v['id']]['update_time'] > $v['update_time']
            && $v['update_time'] = $relateAdminDepartUpdateTime[$v['id']]['update_time'];

//            $v['subject_name'] = empty(Constant::ENUM_SUBJECT[$v['subject']]) ? "未知" : Constant::ENUM_SUBJECT[$v['subject']];
//            $v['grade_part_name'] = empty(Constant::ENUM_GRADE_ALL[$v['grade_part']]) ? "未知" : Constant::ENUM_GRADE_ALL[$v['grade_part']];
            $v['admin_department_list'] = [];
            $v['platform_list'] = [];
            $v['role_id'] = $v['admin'];
            $v['role_name'] = isset($roleEntity[$v['admin']]) ?$roleEntity[$v['admin']]['role_name'] : "未知";
            $v['department_name'] = isset($departmentEntity[$v['department_id']]) ? $departmentEntity[$v['department_id']]['department_name'] : "未知";
            $v['work_type_name'] = isset($workTypeEntity[$v['work_type']]) ? $workTypeEntity[$v['work_type']]['name'] : "未知";
            $v['work_level_name'] = isset($workLevelEntity[$v['work_level']]) ? $workLevelEntity[$v['work_level']]['name'] : "未知";
            $userList[$k] = $v;
        }
        foreach($platformList as $v){
            if(empty($platformEntity[$v['platform_id']])){
                continue;
            }
            $platformInfo = $platformEntity[$v['platform_id']];
            $userList[$v['user_id']]['platform_list'][$v['platform_id']] = [
                'platform_id'=>$v['platform_id'],
                'platform_name'=>$platformInfo['platform_name'],
            ];
        }
        foreach($relateAdminDepart as $v){
            if(empty($departmentEntity[$v['department_id']])){
                continue;
            }
            if(empty($userList[$v['user_id']]['admin_department_list'][$v['department_id']]) ){
                $userList[$v['user_id']]['admin_department_list'][$v['department_id']] = [
                    'department_id'=>$v['department_id'],
                    'department_name'=>$departmentEntity[$v['department_id']]['department_name'],
                    'platform_id'=>[]
                ];
            }
            array_push($userList[$v['user_id']]['admin_department_list'][$v['department_id']]['platform_id'],$v['platform_id']);
        }

        foreach($userList as $k=>$v){
            $v['admin_department_list'] = array_values($v['admin_department_list']);
            $userList[$k] = $v;
        }


        $retData = [
            'page' => $this->page,
            'total' => $total,
            'list' => array_values($userList),
        ];
        return $retData;
    }


    public function del()
    {
        if($ding = DingtalkUser::findOneByWhere(['kael_id'=>$this->id])){
            throw new Exception('无法删除用户,该用户已从钉钉被动同步', Exception::ERROR_COMMON);
        }
        $this->checkUserAuth();
        $oldOne = UserCenter::findOneById($this->id);
        if(empty($oldOne)){
            throw new Exception('用户不存在', Exception::ERROR_COMMON);
        }
        if($oldOne['admin'] == Role::ROLE_ADMIN){
            throw new Exception('无权限删除超级管理员', Exception::ERROR_COMMON);
        }
        LogAuthUser::LogUser($this->user['id'],$this->id,LogAuthUser::OP_DEL_USER,'del');
        UserCenter::updateAll(['status'=>UserCenter::STATUS_INVALID],['id' => $this->id]);
        RelateAdminDepartment::updateAll(['status'=>2],['user_id'=>$this->id,'status'=>0]);
        RelateUserPlatform::updateAll(['status'=>2],['user_id'=>$this->id,'status'=>0]);
        return [];
    }


    /**
     * @return bool|int
     * @throws Exception
     * @throws \Exception
     * 仅创建者可以修改
     */
    public function mixAddEdit()
    {
        $this->checkSuperAuth();

//        if (empty($this->data['center']) || empty($this->data['center']['mobile'])) {
//            throw new Exception('手机号不能为空', Exception::ERROR_COMMON);
//        }
//        if(!preg_match('/^1\d{10}$/',$this->data['center']['mobile'])){
//            throw new Exception('手机号格式不正确', Exception::ERROR_COMMON);
//        }
//        if (empty($this->data['center']['username'])) {
//            throw new Exception('用户名不能为空', Exception::ERROR_COMMON);
//        }
        if(!isset($this->data['center']['admin']) || $this->data['center']['admin'] == -1){
            throw new Exception('请选择权限', Exception::ERROR_COMMON);
        }
        if(!isset($this->data['center']['sex']) || $this->data['center']['sex'] == -1){
            throw new Exception('请选择性别', Exception::ERROR_COMMON);
        }
        if(!isset($this->data['center']['user_type']) || $this->data['center']['user_type'] == -1){
            throw new Exception('请选择用户类型', Exception::ERROR_COMMON);
        }
//        if(!isset($this->data['center']['department_id']) || $this->data['center']['department_id'] <= 0){
//            throw new Exception('请选择部门', Exception::ERROR_COMMON);
//        }
        if(!isset($this->data['center']['work_type']) || $this->data['center']['work_type'] < 0 || $this->data['center']['work_type'] > 10){
            throw new Exception('工种类型错误', Exception::ERROR_COMMON);
        }
        if(!isset($this->data['center']['work_level']) || $this->data['center']['work_level'] < 0 || $this->data['center']['work_level'] > 3){
            throw new Exception('级别类型错误', Exception::ERROR_COMMON);
        }
        $this->data['center']['user_source'] = $this->user_source;
        $this->data['center']['password'] = !empty($this->data['center']['password']) ? md5($this->data['center']['password']) : "";
        if (empty($this->data['center']['password']))
            unset($this->data['center']['password']);

        //固定权限
        $platformListAllow = $this->platformListByAdminDepartment();
        $platformListAllow = array_column($platformListAllow,'platform_id');
        if(empty($this->data['platform_list'])){
            $this->data['platform_list'] = [];
        }else{
            $this->data['platform_list'] = array_intersect($this->data['platform_list'],$platformListAllow);
        }

        if (0 == $this->id) {            //新增用户

            if($this->data['center']['admin'] == Role::ROLE_ADMIN && $this->user['admin'] !== Role::ROLE_ADMIN){
                throw new Exception('无权限新增超级管理员', Exception::ERROR_COMMON);
            }
//            //唯一性
            $old = UserCenter::find()->where(['mobile'=>$this->data['center']['mobile'],'status'=>UserCenter::STATUS_VALID])->one();
            if(!empty($old)){
                throw new Exception('手机号已存在', Exception::ERROR_COMMON);
            }
            if(!empty($this->data['center']['email'])){
                $this->data['center']['email'] = trim($this->data['center']['email']);
                $old = UserCenter::find()->where(['email'=>$this->data['center']['email'],'status'=>UserCenter::STATUS_VALID])->one();
                if(!empty($old)){
                    throw new Exception('邮箱已存在', Exception::ERROR_COMMON);
                }
                if($this->data['center']['user_type'] == 0 && substr($this->data['center']['email'],-11) != '@knowbox.cn'){
                    throw new Exception('员工请使用公司邮箱', Exception::ERROR_COMMON);
                }
                if($this->data['center']['user_type'] == 0 && empty($this->data['center']['work_number'])){
                    throw new Exception('员工请填写工号', Exception::ERROR_COMMON);
                }
            }
//            //新增
            $this->mobile = $this->data['center']['mobile'];
            $userInfo = $this->checkUserMobile();
            if(!empty($userInfo)){
                throw new Exception('用户已存在',Exception::ERROR_COMMON);
            }
            if (empty($this->data['center']['password'])) {
                $this->data['center']['password'] = md5("123456");
            }
//            //新增
            $model = new UserCenter();
            foreach ($this->data['center'] as $k => $v) {
                $model->$k = $v;
            }
            $ret = $model->insert();
            $userId = $model->id;
//            //开通磐石权限
            $this->id = $userId;
            $this->data['platform_list'] = [6000];
            $this->updatePriv();
            LogAuthUser::LogUser($this->user['id'],$userId,LogAuthUser::OP_ADD_USER,$this->data);
            //权限
            RelateUserPlatform::batchAdd($userId,$this->data['platform_list']);
        } else {            //编辑用户
            //编辑
            $oldOne = UserCenter::findOneById($this->id);
            if (empty($oldOne)) {
                throw new Exception("用户不存在", Exception::ERROR_COMMON);
            }
            if($oldOne['admin'] == Role::ROLE_ADMIN && $this->user['admin'] != Role::ROLE_ADMIN){
                throw new Exception('无权限修改超级管理员', Exception::ERROR_COMMON);
            }
            if($this->data['center']['admin'] == Role::ROLE_ADMIN && $this->user['admin'] != Role::ROLE_ADMIN){
                throw new Exception('无权限修改超级管理员', Exception::ERROR_COMMON);
            }
            //唯一性
            if($oldOne['mobile'] != $this->data['center']['mobile']){
                $old = UserCenter::find()->where(['mobile'=>$this->data['center']['mobile'],'status'=>UserCenter::STATUS_VALID])->one();
                if(!empty($old)){
                    throw new Exception('手机号已存在', Exception::ERROR_COMMON);
                }
            }
            if($oldOne['email'] != $this->data['center']['email']){
                if(!empty($this->data['center']['email'])){
                    $old = UserCenter::find()->where(['email'=>$this->data['center']['email'],'status'=>UserCenter::STATUS_VALID])->one();
                    if(!empty($old)){
                        throw new Exception('邮箱已存在', Exception::ERROR_COMMON);
                    }
                }
            }
            if($oldOne['username'] != $this->data['center']['username']){
                $updateParams['username'] = $this->data['center']['username'];
            }
            if($oldOne['mobile'] != $this->data['center']['mobile']){
                $updateParams['mobile'] = $this->data['center']['mobile'];
            }
            if($oldOne['work_number'] != $this->data['center']['work_number']){
                $updateParams['work_number'] = $this->data['center']['work_number'];
            }
            LogAuthUser::LogUser($this->user['id'],$this->id,LogAuthUser::OP_EDIT_USER,$this->data);
            $updateParams = $this->data['center'];
            $updateParams['email_created'] = 0;
            $ret = UserCenter::updateAll($updateParams, ['id' => $this->id]);
//            RelateUserPlatform::updateAll(['status'=>RelateUserPlatform::STATUS_INVALID],['user_id' => $this->id,'platform_id'=>$platformListAllow]);
//            RelateUserPlatform::batchAdd($this->id,$this->data['platform_list']);
        }
        return $ret;
    }

    public function updatePriv(){
        $this->checkUserAuth();

        //编辑
        $oldOne = UserCenter::findOneById($this->id);
        if (empty($oldOne)) {
            throw new Exception("用户不存在", Exception::ERROR_COMMON);
        }

        //固定权限
        $platformListAllow = $this->platformListByAdminDepartment($oldOne['department_id']);
        $platformListAllow = array_column($platformListAllow,'platform_id');
        if(empty($this->data['platform_list'])){
            $this->data['platform_list'] = [];
        }else{
            $this->data['platform_list'] = array_intersect($this->data['platform_list'],$platformListAllow);
        }



//        RelateUserPlatform::updateAll(
//            ['status'=>RelateUserPlatform::STATUS_INVALID,'delete_user'=>$this->user['id']],
//            ['user_id' => $this->id,'platform_id'=>$platformListAllow,'status'=>RelateUserPlatform::STATUS_VALID]);

        $oldPlatformIds = array_column(RelateUserPlatform::findListByUserPlatform($this->id,$platformListAllow),'platform_id');
        $delPlatforms = array_diff($oldPlatformIds,$this->data['platform_list']);
        $addPlatforms = array_diff($this->data['platform_list'],$oldPlatformIds);
        $trans = RelateUserPlatform::getDb()->beginTransaction();
        try{
            !empty($addPlatforms) && RelateUserPlatform::batchAdd($this->id,$addPlatforms,$this->user['id']);
            !empty($delPlatforms) && RelateUserPlatform::updateAll(
                ['status'=>RelateUserPlatform::STATUS_INVALID,'delete_user'=>$this->user['id']],
                ['user_id' => $this->id,'platform_id'=>$delPlatforms,'status'=>RelateUserPlatform::STATUS_VALID]);
            LogAuthUser::LogUser($this->user['id'],$this->id,LogAuthUser::OP_EDIT_USER_ROLE,$this->data);
            $trans->commit();
        }catch (\Exception $e){
            $trans->rollBack();
        }
        return true;
    }

    /**
     *  批量导入用户
     */
    public function actionImportUser()
    {
        $this->checkUserAuth();
        $filePath = $_FILES['file']['tmp_name'][0];
        $PHPReader = new \PHPExcel_Reader_Excel2007(); // Reader很关键，用来读excel文件
        if (!$PHPReader->canRead($filePath)) { // 这里是用Reader尝试去读文件，07不行用05，05不行就报错。注意，这里的return是Yii框架的方式。
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($filePath)) {
                $errorMessage = "Can not read file.";
                array_push($error, $errorMessage);
                return $error;
            }
        }
        $objPHPExcel = $PHPReader->load($filePath); // Reader读出来后，加载给Excel实例
        $data = $objPHPExcel->getSheet(0)->toArray();
        $error = array();
        $paramsUcenter = [];

        $allDepartment = Department::findAllList();

        $startUserId = UserCenter::find()->select('id')->orderBy('id desc')->limit(1)->asArray(true)->one();
        $startUserId = empty($startUserId) ? 0 : $startUserId['id'];
        $startUserId = $startUserId + 100;

        foreach ($data as $k => $v) {
            if ($k == 0) {
                continue;//标题行
            }
            $v = array_map('strval',$v);
            $v = array_map('trim',$v);

            if (empty($v[0])) {
                array_push($error, '第' . ($k + 1) . '行，姓名不存在');
                continue;
            }
            if (empty($v[1])) {
                array_push($error, '第' . ($k + 1) . '行，手机号不存在');
                continue;
            }
            if (empty($v[3]) || !is_numeric($v[3])) {
                array_push($error, '第' . ($k + 1) . '行，性别不存在');
                continue;
            }
            if (empty($v[4]) || !isset($allDepartment[intval($v[4])])) {
                array_push($error, '第' . ($k + 1) . '行，部门不存在');
                continue;
            }

            $MobileOnly = UserCenter::findByMobile($v[1]);
            if (!empty($MobileOnly)) {
                array_push($error, '第' . ($k + 1) . '行，电话号码已存在不能重复添加');
                continue;
            }
            isset($v[2]) && $v[2] = trim($v[2]);
            if(!empty($v[2])){
                $emailOnly = UserCenter::find()->where(['status'=>0,'email'=>$v[2]])->asArray(true)->one();
                if (!empty($emailOnly)) {
                    array_push($error, '第' . ($k + 1) . '行，邮箱已存在不能重复添加');
                    continue;
                }
                if($allDepartment[intval($v[4])]['is_outer'] == 0 && substr($v[2],-11) != '@knowbox.cn'){
                    array_push($error, '第' . ($k + 1) . '行，员工请使用公司邮箱');
                    continue;
                }
            }

            if($allDepartment[intval($v[4])]['is_outer'] == 0 && empty($v[12])){
                array_push($error, '第' . ($k + 1) . '行，员工请填写工号');
                continue;
            }
            if (!empty($v[12])) {
                $work_number = UserCenter::find()->where(['status'=>0, 'work_number'=>$v[12]])->asArray(true)->one();
                if (!empty($work_number)) {
                    array_push($error, '第' . ($k + 1) . '行，工号已被人占用');
                    continue;
                }
            }

            $this->user_source = "admin";

            /*
             * $title = [
            '姓名',0
            '手机号',1
            '邮箱',2
            '性别(1:男；2:女)',3
            '所属部门('.$deparmentStr.')',4
            "身份证号",5
            '银行名称',6
            '银行区域',7
            '银行卡类型'8,
            '银行卡号',9
//            '学科(0:数学；1:语文；2:英语；3:物理；4:化学；5:生物；6:历史；7:地理；8:政治；9:信息技术)',10
//            '学段(10:小学;20:初中;30:高中;)',11
        ];
             */

            $paramsUcenter[$k] = [
                'id'=>$startUserId + $k,
                'username' => trim($v[0]),
                'mobile' => trim($v[1]),
                'email'=>trim($v[2]),
                'sex' => $v[3],
                'department_id'=>$v[4],
                'idcard' => $v[5],
                'bank_name' => $v[6],
                'bank_area' => $v[7],
                'bank_deposit' => $v[8],
                'bank_account' => $v[9],
                'user_source' => $this->user_source,
                'user_type' => $allDepartment[intval($v[4])]['is_outer'],//0内部员工 1外包
                'admin_id' => $this->user['id'],
                'work_level'  => $v[10] ?? 0,
                'work_type'   => $v[11] ?? 0,
                'work_number' => $v[12],
                'password'    => empty($v[13]) ? md5('1234567') : md5($v[13]),
            ];
        }
        if(!empty($paramsUcenter)){
            $allMobile = array_values(array_filter(array_column($paramsUcenter,'mobile')));
            $allEmail = array_values(array_filter(array_column($paramsUcenter,'email')));
            $allWorkNumber = array_values(array_filter(array_column($paramsUcenter,'work_number')));
            if(count($allMobile) != count(array_unique($allMobile))){
                throw new Exception("表格中手机号存在重复");
            }
            if(count($allEmail) != count(array_unique($allEmail))){
                throw new Exception("表格中非空邮箱存在重复");
            }
            if(count($allWorkNumber) != count(array_unique($allWorkNumber))){
                throw new Exception("表格中非空工号存在重复");
            }

            $columns = array_keys(array_values($paramsUcenter)[0]);
            $rows = [];
            foreach($paramsUcenter as $v){
                $rows[] = array_values($v);
            }
            UserCenter::batchInsertAll(UserCenter::tableName(),$columns,$rows,UserCenter::getDb());
            LogAuthUser::LogUser($this->user['id'],array_column($paramsUcenter,'id'),LogAuthUser::OP_ADD_USER,"import");
        }else{
            array_push($error, '没有有效数据');
        }
        if (!empty($error)) {
            $error = join('----------', $error);
            throw new Exception($error, Exception::ERROR_COMMON);
        } else {
            return "导入成功";
        }

    }

    //下载格式模板
    public function Download()
    {
        $this->checkSuperAuth();
        $platformAll = Platform::findAllList();
        $platfromStr = array_map(function($v){return $v['platform_id'].':'.$v['platform_name'];},$platformAll);
        $platfromStr = join('；',$platfromStr);
        $deparmentAll = Department::findAllList();
        $deparmentStr = array_map(function($v){return $v['department_id'].':'.$v['department_name'];},$deparmentAll);
        $deparmentStr = join('；',$deparmentStr);
        $title = [
            '姓名',
            '手机号',
            '邮箱',
            '性别(1:男；2:女)',
            '所属部门('.$deparmentStr.')',
            "身份证号",
            '银行名称',
            '银行区域',
            '银行卡类型',
            '银行卡号',
            '职级(1:初级; 2:中级; 3:高级; 4:未知)',
            '工种(1:BD; 2:运营; 3:市场; 4:研发; 5:产品; 6:设计; 7:职能; 8:教学; 9:教研; 10:创始人; 11:未知)',
            '工号',
            '密码',
//            '学科(0:数学；1:语文；2:英语；3:物理；4:化学；5:生物；6:历史；7:地理；8:政治；9:信息技术)',
//            '学段(10:小学;20:初中;30:高中;)',
//            '平台权限(逗号分割)('.$platfromStr.')'
        ];

        $excelData = [];
        $excelData[] = $title;

        $objPHPExcel = new \PHPExcel();
        $objSheet = $objPHPExcel->getActiveSheet();
        $objSheet->setTitle('批量新增格式模板');
        $objSheet->fromArray($excelData);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="格式模版.xls"');
        header('Cache-Control: max-age=1');
        $objWriter->save('php://output');
        return '';
    }

    public function DownloadPriv()
    {
        $this->checkUserAuth();
        $platformAll = $this->platformList();
        $platfromStr = array_map(function($v){return $v['platform_id'].':'.$v['platform_name'];},$platformAll);
        $platfromStr = join('；',$platfromStr);
        $title = [
            '用户名（可不填,修改无效）',
            '手机号',
            '邮箱（邮箱和手机号必须填写一个）',
            '新增平台权限(逗号分割)('.$platfromStr.')',
            '清除平台权限(逗号分割)('.$platfromStr.')',

        ];

        $excelData = [];
        $excelData[] = $title;

        $getDepartmentId = intval(Yii::$app->request->get('department_id',0));
        if($getDepartmentId > 0){
            //有效部门
            $userList = UserCenter::findListByDepartment($getDepartmentId,'mobile,email,username');
            foreach($userList as $v){
                $excelData[] = [$v['username'],$v['mobile'],$v['email'],'',''];
            }
        }

        $objPHPExcel = new \PHPExcel();
        $objSheet = $objPHPExcel->getActiveSheet();
        $objSheet->setTitle('error');
        $objSheet->fromArray($excelData);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="权限格式模版.xls"');
        header('Cache-Control: max-age=1');
        $objWriter->save('php://output');
        return '';
    }




    public function downloadPrivNew()
    {

        $this->checkUserAuth();
        $this->filter = Yii::$app->request->get('filter',"");
        $this->filter = json_decode($this->filter,true);
        empty($this->filter) && $this->filter = [];
        $search = !empty($this->filter['search']) ? trim($this->filter['search']) : "";
        $where = [];
        isset($this->filter['role']) && $this->filter['role'] != -1 && $where['admin'] = $this->filter['role'];
        isset($this->filter['department']) && $this->filter['department'] != -1 && $where['department_id'] = $this->filter['department'];
        if(isset($this->filter['department']) && $this->filter['department'] == -1){
            if($this->user['admin'] == Role::ROLE_DEPARTMENT_ADMIN){
                $departmentList = RelateAdminDepartment::findListByAdmin($this->user['id']);
                $where['department_id'] = array_column($departmentList,'department_id');
            }
        }
        isset($this->filter['user_type']) && $this->filter['user_type'] != -1 && $where['user_type'] = $this->filter['user_type'];

        $leftjoin = [];
        if(isset($this->filter['platform']) && $this->filter['platform'] != -1){
            $leftjoin[] = [RelateUserPlatform::tableName().' b','a.id = b.user_id'];
            $where['b.platform_id'] = $this->filter['platform'];
        }
        $selectColumn = 'a.id,a.mobile,a.email,a.username';
        $userList = UserCenter::findUserSearch(1,50000,$search,$where,$leftjoin,$selectColumn);

        //excel
        $platformAll = $this->platformList();
        $platformData = [];
        $platformData[] = ['填写说明：',''];
        $platformData[] = ['用户名可不填，仅查看使用，修改无效',''];
        $platformData[] = ['手机号和邮箱作为用户认定条件，至少填写一个（当手机号存在对应用户是以手机号为准）',''];
        $platformData[] = ['新增/清除平台权限时可选多个，用英文,分割',''];
        $platformData[] = ['只可修改自己有权限修改的平台权限，其余无效',''];
        $platformData[] = ['',''];
        $platformData[] = ['平台列表如下：',''];
        $platformData[] = ['平台ID','平台名称'];
        foreach($platformAll as $v){
            $platformData[] = [$v['platform_id'],$v['platform_name']];
        }

        $title = [
            '用户名',
            '手机号',
            '邮箱',
            '新增平台权限(逗号分割)',
            '清除平台权限(逗号分割)',
        ];

        $excelData = [];
        $excelData[] = $title;
        foreach($userList as $v){
            $excelData[] = [$v['username'],$v['mobile'],$v['email'],'',''];
        }

        $objPHPExcel = new \PHPExcel();
        $objSheet = $objPHPExcel->createSheet(0);
        $objSheet->setTitle('用户列表');
        $objSheet = $objPHPExcel->createSheet(1);
        $objSheet->setTitle('填写说明');

        $objSheet = $objPHPExcel->getSheet(1);
        $objSheet->fromArray($platformData);
        $objSheet = $objPHPExcel->getSheet(0);
        $objSheet->fromArray($excelData);
        $objPHPExcel->setActiveSheetIndex(0);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="批量添加权限.xls"');
        header('Cache-Control: max-age=1');
        $objWriter->save('php://output');
        return '';

    }




    public function importUserPriv()
    {
        set_time_limit(0);
        $this->checkUserAuth();
        $filePath = $_FILES['file']['tmp_name'][0];
        $PHPReader = new \PHPExcel_Reader_Excel2007(); // Reader很关键，用来读excel文件
        if (!$PHPReader->canRead($filePath)) { // 这里是用Reader尝试去读文件，07不行用05，05不行就报错。注意，这里的return是Yii框架的方式。
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($filePath)) {
                $errorMessage = "Can not read file.";
                array_push($error, $errorMessage);
                return $error;
            }
        }
        $objPHPExcel = $PHPReader->load($filePath); // Reader读出来后，加载给Excel实例
        $data = $objPHPExcel->getSheet(0)->toArray();
        $error = array();

        $mobileList = [];
        $emailList = [];
        $userList  = [];
        foreach($data as $k=>$v){
            if($k == 0){
                $title = [
                    '用户名',
                    '手机号',
                    '邮箱',
                    '新增平台权限(逗号分割)',
                    '清除平台权限(逗号分割)',
                ];
                foreach($title as $titleIndex=>$titleValue){
                    if(empty($v[$titleIndex])){
                        throw new Exception("请不要修改原始表结构",Exception::ERROR_COMMON);
                    }
                    if($v[$titleIndex] != $titleValue){
                        throw new Exception("请不要修改原始表结构",Exception::ERROR_COMMON);
                    }
                }
                continue;
            }
            $v = array_map('strval',$v);
            $v = array_map('trim',$v);
            if(!empty($v[1])){
                //mobile
                if(!is_numeric($v[1])){
                    throw new Exception("请检查手机号格式",Exception::ERROR_COMMON);
                }
                array_push($mobileList,$v[1]);
            }elseif(!empty($v[2])){
                if(strpos($v[2],'@') === false){
                    throw new Exception("请检查邮箱格式",Exception::ERROR_COMMON);
                }
                array_push($emailList,$v[2]);
            }
        }
        !empty($mobileList) && $userList = array_merge($userList,UserCenter::find()->where(['mobile'=>$mobileList,'status'=>0])->asArray(true)->all());
        !empty($emailList) && $userList = array_merge($userList,UserCenter::find()->where(['email'=>$emailList,'status'=>0])->asArray(true)->all());
        $departmentIdList = array_column($userList,'department_id');
        $userListMobile = [];
        $userListEmail = [];
        foreach($userList as $v){
            !empty($v['mobile']) && $userListMobile[$v['mobile']] = $v;
            !empty($v['email']) && $userListEmail[$v['email']] = $v;
        }

//        $departmentPrivList = [];
//        foreach($departmentIdList as $departmentIdOne){
//            $privOneList = $this->platformListByAdminDepartment($departmentIdOne);
//            $departmentPrivList[$departmentIdOne] = array_column($privOneList,'platform_id');
//        }

        $addColumn = ['user_id','platform_id','create_user'];
        $addRows = [];
        $delRelate =[];
        $logPlatAuthAdd = [];
        $logPlatAuthDel = [];

        foreach ($data as $k => $v) {
            if ($k == 0) {
                continue;//标题行
            }
            $v = array_map('strval',$v);
            $v = array_map('trim',$v);

            if(empty($v[0]) && empty($v[1]) && empty($v[2]) && empty($v[3]) && empty($v[4])){
                continue;
            }

            if (empty($v[1]) && empty($v[2])) {
                array_push($error, '第' . ($k + 1) . '行，手机和邮箱不能同时为空');
                continue;
            }

            if(!empty($v[1])){
                $uname = $v[1];
                if(!isset($userListMobile[$v[1]])){
                    array_push($error, '第' . ($k + 1) . '行，用户不存在'.$uname);
                    continue;
                }
                $userInfo = $userListMobile[$v[1]];
            }elseif(!empty($v[2])){
                $uname = $v[2];
                if(!isset($userListEmail[$v[2]])){
                    array_push($error, '第' . ($k + 1) . '行，用户不存在'.$uname);
                    continue;
                }
                $userInfo = $userListEmail[$v[2]];
            }
            //增加
            if(!empty($v[3])){
                $addPrivIds = explode(',',$v[3]);//增加
//                $departmentIdOne = $userInfo['department_id'];
//                $diff = array_diff($addPrivIds,$departmentPrivList[$departmentIdOne]);
//                if(!empty($diff)){
//                    //权限不足
//                    array_push($error, '第' . ($k + 1) . '行，对用户该平台无权限'.$uname);
//                    continue;
//                }
                $userOldPrivs = array_column(RelateUserPlatform::findListByUserPlatform($userInfo['id'],$addPrivIds),'platform_id');
                $logPlatAuthAdd[$userInfo['id']] = $addPrivIds;
                foreach($addPrivIds as $privOne){
                    if(!in_array($privOne,$userOldPrivs)){
                        $addRows[] = [$userInfo['id'],$privOne,$this->user['id']];
                    }
//                    $delRelate[] = ['user_id'=>$userInfo['id'],'platform_id'=>$privOne];
//                    $addRows[] = [$userInfo['id'],$privOne,$this->user['id']];
                }
            }

            //删除
            if(!empty($v[4])){
                $delPriv = explode(',',$v[4]);//增加
//                $departmentIdOne = $userInfo['department_id'];
//                $diff = array_diff($delPriv,$departmentPrivList[$departmentIdOne]);
//                if(!empty($diff)){
//                    //权限不足
//                    array_push($error, '第' . ($k + 1) . '行，对用户该平台无权限'.$uname);
//                    continue;
//                }
                $logPlatAuthDel[$userInfo['id']] = $delPriv;
                foreach($delPriv as $privOne){
                    $delRelate[] = ['user_id'=>$userInfo['id'],'platform_id'=>$privOne,'status'=>RelateUserPlatform::STATUS_VALID];
                }
            }

        }


        $trans = RelateUserPlatform::getDb()->beginTransaction();
        try{
            foreach($delRelate as $delWhere){
                RelateUserPlatform::updateAll(['status'=>RelateUserPlatform::STATUS_INVALID,'delete_user'=>$this->user['id']],$delWhere);
            }

            if(!empty($addRows)){
                RelateUserPlatform::batchInsertAll(
                    RelateUserPlatform::tableName(),
                    $addColumn,
                    $addRows,
                    RelateUserPlatform::getDb(),
                    'INSERT'
                );
            }
            //log
            foreach($logPlatAuthAdd as $k=>$v){
                LogAuthUser::LogUser($this->user['id'],$k,LogAuthUser::OP_ADD_USER_ROLE,$v);
            }
            foreach($logPlatAuthDel as $k=>$v){
                LogAuthUser::LogUser($this->user['id'],$k,LogAuthUser::OP_DEL_USER_ROLE,$v);
            }
            $trans->commit();
        }catch(\Exception $e){
            $trans->rollBack();
            throw $e;
        }

        if (!empty($error)) {
            $error = join("\n", $error);
            throw new Exception($error, Exception::ERROR_COMMON);
        } else {
            return "导入成功";
        }

    }
}
