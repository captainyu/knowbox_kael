<?php
namespace console\controllers;

use common\libs\DingTalkApi;
use common\libs\DingTalkApiJZ;
use common\libs\IOApi;
use common\models\CommonUser;
use common\models\DingtalkDepartment;
use common\models\DingtalkDepartmentUser;
use common\models\DingtalkHrmUser;
use common\models\DingtalkHrmUserLeave;
use common\models\DingtalkHrmUserLeaveDept;
use common\models\DingtalkUser;
use common\models\DepartmentRelateToKael;
use common\models\ehr\BusinessDepartment;
use common\models\RelateDingtalkDepartmentPlatform;
use common\models\RelateUserPlatform;
use common\models\UserCenter;
use usercenter\components\exception\Exception;
use yii\console\Controller;

class DingNewController extends Controller
{

    //测试
    public function actionTest(){
//        $userInfoList = DingTalkApi::getDepartmentUserInfoList(1);
//        echo json_encode($userInfoList);

//        $callback = DingTalkApi::callBackQuery();
//        try{
//            $callback2 = DingTalkApiJZ::callBackQuery();
//        }catch (\Exception $e){
//            $callback2 = $e->getMessage();
//        }
//
//        $processList = DingTalkApi::getCheckProcessList();
//        $processList2 = DingTalkApiJZ::getCheckProcessList();
//        echo json_encode(['p1'=>$processList,'p2'=>$processList2,'c1'=>$callback,'c2'=>$callback2],64|256);

        $uids = DingTalkApi::getHrmLizhiUids();
        echo json_encode($uids)."\n";
        echo "count:".count($uids)."\n";
        $userInfo = DingTalkApi::getHrmLizhiUserInfo($uids);
        echo json_encode($userInfo,64|256)."\n";
    }


    /**
     * 初始化钉钉信息
     */
    public function actionUpdate(){
        if(exec('ps -ef|grep "ding-new/update"|grep -v grep | grep -v cd | grep -v "/bin/sh"  |wc -l') > 1){
            echo "is_running";
            exit();
        }
        try{

            echo date('Y-m-d H:i:s')."\t开始同步钉钉部门到kael\n";
            $this->updateDingDepartment(1);
            echo date('Y-m-d H:i:s')."\t部门同步结束\n";

            echo date('Y-m-d H:i:s')."\t开始同步兼职团队钉钉部门到kael\n";
            $this->updateDingDepartment(2);
            echo date('Y-m-d H:i:s')."\t部门同步兼职团队结束\n";

            sleep(1);

            echo date('Y-m-d H:i:s')."\t开始同步钉钉人员到kael\n";
            $this->updateDingDepartmentUser(1);
            echo date('Y-m-d H:i:s')."\t员工同步结束\n";

            echo date('Y-m-d H:i:s')."\t开始同步兼职团队钉钉人员到kael\n";
            $this->updateDingDepartmentUser(2);
            echo date('Y-m-d H:i:s')."\t员工同步兼职团队结束\n";

            //更新基地
//            $this->updateDingUserBaseName();

            sleep(1);

        }catch (\Exception $e){
            throw $e;
        }
    }

    public function actionBaseName(){
        if(exec('ps -ef|grep "ding-new/base-name"|grep -v grep | grep -v cd | grep -v "/bin/sh"  |wc -l') > 1){
            echo "is_running";
            exit();
        }
        $this->updateDingUserBaseName();
    }

    private function updateDingUserBaseName(){
        $allDingtalkUser = DingtalkDepartmentUser::find()
            ->select('kael_id,base_name')
            ->where(['status'=>0])
            ->andWhere('kael_id > 0')
            ->andWhere(['!=','base_name',''])
            ->orderBy('corp_type desc, create_time desc')
            ->asArray(true)->all();
        $kaelIdToBaseName = [];
        foreach ($allDingtalkUser as $v){
            if(!empty($v['kael_id']) && empty($kaelIdToBaseName[$v['kael_id']])){
                $kaelIdToBaseName[$v['kael_id']] = $v['base_name'];
            }
        }
        $allList = DingtalkUser::findList([]);
        foreach ($allList as $v){
            $baseName = $kaelIdToBaseName[$v['kael_id']] ?? '';
            if($v['base_name'] != $baseName){
                try{
                    echo "update base name {$v['kael_id']}-{$baseName}\n";
                    $ret = IOApi::updateUserBaseName($v['kael_id'],$baseName);
                    echo "io ret ".json_encode($ret,64|256)."\n";
                    DingtalkUser::updateAll(['base_name'=>$baseName],['kael_id'=>$v['kael_id']]);
                }catch (\Exception $e){
                    echo $e->getMessage()."\n";
                }
            }else{
                echo "not change {$v['kael_id']}\n";
            }
        }
    }


    private function updateDingDepartment($corpType){
        if($corpType == 1){
            $allDepartmentList = DingTalkApi::getDepartmentAllList();
        }elseif($corpType == 2){
            $allDepartmentList = DingTalkApiJZ::getDepartmentAllList();
        }else{
            throw new Exception("不支持的企业");
        }
        $allIds = array_column($allDepartmentList,'id');
        DingtalkDepartment::updateAll(['corp_type'=>$corpType,'status'=>0],['id'=>$allIds]);

        $oldDepartments = array_column(
            DingtalkDepartment::find()
                ->select('*')
                ->where(['status'=>0,'corp_type'=>$corpType])
                ->asArray(true)->all(),
            null,
            'id'
        );
        $oldDepartmentIds = array_keys($oldDepartments);
        $oldDepartmentIds = array_map('intval',$oldDepartmentIds);
        $delIds = array_diff($oldDepartmentIds,$allIds);
        $insertIds  = array_diff($allIds,$oldDepartmentIds);
        echo date('Y-m-d H:i:s')."\t新增部门如下:\n";
        echo json_encode($insertIds)."\n";
        echo date('Y-m-d H:i:s')."\t需要删除部门如下:\n";
        echo json_encode($delIds)."\n";
        $columns = ['id','name','alias_name','parentid','corp_type'];
        $rows = [];
        foreach ($allDepartmentList as $v){
            if(in_array($v['id'],$oldDepartmentIds)){
                $params = ['name'=>$v['name'],'parentid'=>$v['parentid']];
                if(empty($oldDepartments[$v['id']]['alias_name'])){
                    $params['alias_name'] = $v['name'];
                }elseif($corpType == 2){
                    $params['alias_name'] = $v['name'];
                }
                if($oldDepartments[$v['id']]['main_leader_id'] && ! $user = DingtalkUser::findOneByWhere(['kael_id'=>$oldDepartments[$v['id']]['main_leader_id']])){
                    echo date('Y-m-d H:i:s')."\t部门负责人离职,重置为空,部门编号:".$v['id']."\t原部门负责人编号:".$oldDepartments[$v['id']]['main_leader_id']."\n";
                    $params['main_leader_id'] = 0;
                    $params['main_leader_name'] = '';
                }
                DingtalkDepartment::updateAll($params,['id'=>$v['id']]);
            }elseif(in_array($v['id'],$insertIds)){
                $rows[] = [$v['id'],$v['name'],$v['name'],$v['parentid'],1];
            }
        }
        !empty($rows) && DingtalkDepartment::batchInsertAll(DingtalkDepartment::tableName(),$columns,$rows,DingtalkDepartment::getDb(),'INSERT IGNORE');
        if(!empty($delIds)){
            DingtalkDepartment::updateAll(['status'=>1],['id'=>$delIds]);
            DepartmentRelateToKael::updateAll(['status'=>1],['department_id'=>$delIds]);
        }
        //更新level
        $sql = "update dingtalk_department set `level` = 1,`subroot_id` = id,`path_name` = alias_name,`path_id`=concat('|',{$corpType},'|',id,'|') where status = 0 and parentid = 1 and corp_type={$corpType}";
        DingtalkDepartment::getDb()->createCommand($sql)->execute();
        $sql = "update dingtalk_department set `path_name_ding` = name where status = 0 and parentid = 1 and corp_type={$corpType}";
        DingtalkDepartment::getDb()->createCommand($sql)->execute();
        for($level =1 ; $level <= 10; $level++){
            $sql = "update dingtalk_department a left join dingtalk_department b on a.parentid = b.id set a.`level` = b.level + 1,a.`subroot_id` = b.subroot_id,a.path_name = concat(b.path_name,'/',a.alias_name),a.path_id = concat(b.path_id,a.id,'|') where a.status = 0 and b.status = 0 and b.`level`={$level} and b.corp_type={$corpType}";
            DingtalkDepartment::getDb()->createCommand($sql)->execute();
            $sql = "update dingtalk_department a left join dingtalk_department b on a.parentid = b.id set a.path_name_ding = concat(b.path_name_ding,'/',a.name) where a.status = 0 and b.status = 0 and b.`level`={$level} and b.corp_type={$corpType}";
            DingtalkDepartment::getDb()->createCommand($sql)->execute();
        }
        //更新基地
        sleep(1);
        $dingDeptList = DingtalkDepartment::findList(['corp_type'=>$corpType],'','id,path_name,base_name');
        $baseList = ['北京','长春','西安','唐山','湘潭','武汉','长沙','上海','BD','合肥'];
        foreach ($dingDeptList as $v){
            $pathName = explode('/',$v['path_name']);
            $base = '';
            if(false === mb_strpos($v['path_name'],'外派')){
                foreach ($pathName as $pathOne){
                    $pathOneFst = mb_substr($pathOne,0,2);
                    if(in_array($pathOneFst,$baseList)){
                        $base = $pathOne;
                        break;
                    }
                }
            }
            $base != $v['base_name'] && DingtalkDepartment::updateAll(['base_name'=>$base],['id'=>$v['id']]);
        }
    }

    private function updateDingDepartmentUser($corpType)
    {
        //所有企业员工
        $allDepartmentUserAll = DingtalkDepartmentUser::findList(['corp_type'=>$corpType], '', 'user_id,department_id,relate_id');
        $allDepartmentUserIndex = [];
        foreach ($allDepartmentUserAll as $v){
            $allDepartmentUserIndex[$v['department_id'].'|'.$v['user_id']] = $v['relate_id'];
        }
        //所有kael信息
        $kaelInfoList = CommonUser::find()->select('id,mobile')->where(['status'=>0])->asArray(true)->all();
        $mobileToKaelId = array_column($kaelInfoList,'id','mobile');
        $i = 0;
        for ($level = 0; $level < 10; $level++) {
            echo "level: ".$level."\n";
            if($level == 0){
                $departmentList = [
                    ['id'=>1,'name'=>'小盒科技','base_name'=>'']
                ];
            }else{
                $departmentList = DingtalkDepartment::find()
                    ->where(['status' => 0, 'level' => $level,'corp_type'=>$corpType])
                    ->orderBy('id')
                    ->asArray(true)
                    ->all();
            }
            foreach ($departmentList as $v) {
                echo "dept: {$v['id']}\n";
                $i++;
                if($corpType == 1){
                    $userInfoList = DingTalkApi::getDepartmentUserInfoList($v['id']);
                }elseif($corpType == 2){
                    $userInfoList = DingTalkApiJZ::getDepartmentUserInfoList($v['id']);
                }else{
                    throw new Exception("不支持的企业");
                }
                echo date('Y-m-d H:i:s') . "\t同步部门人员：{$v['name']}[{$v['id']}]\n";
                $mainLeaderId = 0;
                $mainLeaderName = "";
                foreach ($userInfoList as $userInfo) {
                    echo "第" . $i . "次执行*******\n";

                    $userId = $userInfo['userid'];

                    if(empty($userInfo['mobile'])){
                        echo "没有手机号-{$userId}\n";
                        continue;
                    }

                    $relateId = 0;
                    if(isset($allDepartmentUserIndex[$v['id'].'|'.$userId])){
                        $relateId = $allDepartmentUserIndex[$v['id'] . '|' . $userId];
                        unset($allDepartmentUserIndex[$v['id'].'|'.$userId]);
                    }

                    $userParams = [
                        'corp_type' => $corpType,
                        'department_id' => $v['id'],
                        'base_name'=>$v['base_name'],
                        'kael_id'=>$mobileToKaelId[$userInfo['mobile']] ?? 0,
                        'user_id' => $userId,
                        'union_id' => $userInfo['unionid'],
                        'open_id' => $userInfo['openid'] ?? '',
                        'mobile' => $userInfo['mobile'],
                        'state_code' => $userInfo['stateCode']??'',
                        'tel' => $userInfo['tel'] ?? '',
                        'work_place' => $userInfo['workplace']??'',
                        'remark' => $userInfo['remark']??'',
                        'order' => $userInfo['order'] ?? 0,
                        'is_admin' => $userInfo['isAdmin'] ? 1 : 0,
                        'is_boss' => $userInfo['isBoss'] ? 1 : 0,
                        'is_hide' => $userInfo['isHide'] ? 1 : 0,
                        'is_leader' => $userInfo['isLeader'] ? 1 : 0,
                        'name' => $userInfo['name'],
                        'active' => $userInfo['active'] ? 1 : 0,
                        'department' => join(',', $userInfo['department']),
                        'position' => $userInfo['position'] ?? '',
                        'email' => $userInfo['email'] ?? '',
                        'org_email' => $userInfo['orgEmail'] ?? '',
                        'avatar' => $userInfo['avatar'] ?? '',
                        'hired_date'=>empty($userInfo['hiredDate']) ? '0000-00-00 00:00:00' : date('Y-m-d', $userInfo['hiredDate'] / 1000),
                        'job_number' => $userInfo['jobnumber'] ?? '',
                        'ext_attr' => json_encode($userInfo['extattr']??new \stdClass(), 64 | 256),
                        'status' => 0
                    ];
                    if (!empty($relateId)) {
                        echo date('Y-m-d H:i:s') . "\t更新钉钉员工 {$userInfo['name']}[{$userInfo['userid']}]\n";
                        DingtalkDepartmentUser::updateAll($userParams, ['relate_id' => $relateId]);
                    } else {
                        echo date('Y-m-d H:i:s') . "\t新增钉钉员工 {$userInfo['name']}[{$userInfo['userid']}]\n";
                        DingtalkDepartmentUser::add($userParams);
                    }
                    if($userParams['is_leader']){
                        $mainLeaderId = $userParams['kael_id'];
                        $mainLeaderName = $userParams['name'];
                    }
                }
                DingtalkDepartment::updateAll(['main_leader_id' => $mainLeaderId, 'main_leader_name' => $mainLeaderName], ['id' => $v['id'],'status'=>0]);
                BusinessDepartment::updateAll(['main_leader_id' => $mainLeaderId, 'main_leader_name' => $mainLeaderName], ['depart_id' => $v['id'],'status'=>0]);
            }
        }

        //del others
        if(!empty($allDepartmentUserIndex)){
            $delRelateIds = array_values($allDepartmentUserIndex);
            DingtalkDepartmentUser::updateAll(['status'=>1],['relate_id'=>$delRelateIds]);
        }
    }




//    private function updateDingDepartmentPathName(){
//        for($level =1 ; $level <= 10; $level++){
//            if($level==1){
//                $sql = "update dingtalk_department set path_name=alias_name where level ={$level} and status=0;";
//            }else{
//                $sql = <<<SQL
//update  dingtalk_department s
//left join dingtalk_department p on s.parentid = p.id
//set s.path_name = concat(p.path_name,'/',s.alias_name)
//where s.status = 0 and p.status=0 and s.level={$level};
//SQL;
//            }
//
//            DingtalkDepartment::getDb()->createCommand($sql)->execute();
//        }
//
//    }


    private function updateDingUser(){
        //department
        $allDepartmentList = DingtalkDepartment::findList();
        $allDepartmentListIndex = array_column($allDepartmentList,null,'id');
        //dinguser
        $allDingUserList = DingtalkUser::findList();
        $allDingUserListIndex = array_column($allDingUserList,null,'user_id');
        //hrmuser
        $allHrmUserList = DingtalkHrmUser::findList();
        $allHrmUserListIndex = [];
        foreach ($allHrmUserList as $v){
            $allHrmUserListIndex[$v['corp_type'].'-'.$v['user_id']] = $v;
        }
        //departmentuser
        $allDepartmentUserList = DingtalkDepartmentUser::findList();
        $allDepartmentUserListIndex = [];
        foreach ($allDepartmentUserList as $v){
            $allDepartmentUserListIndex[$v['mobile']][] = $v;
        }

        foreach ($allDepartmentUserListIndex as $mobile=>$v1List){
            $departmentsArr = [];
            $userIdsArr = [];
            $mainUserId = 0;
            $mainCorpType = 0;
            $mainCorpDeptIds = [];
            $oldHrms = [];
            $oldDingtalkUser = [];
            $mainUserInfo = [];
            $birthDay = '';
            $hireDay = '';
            foreach ($v1List as $v1){
                if(empty($mainCorpType) || ($v1['corp_type'] == 1 && $mainCorpType != 1)){
                    $mainCorpDeptIds = [];
                    $mainCorpType = $v1['corp_type'];
                    $mainUserId = $v1['user_id'];
                    $mainUserInfo = $v1;
                    $v1['hired_date'] != '0000-00-00 00:00:00' && $hireDay = $v1['hired_date'];
                }
                empty($oldHrms[$v1['corp_type']]) && $oldHrms[$v1['corp_type']] = $allHrmUserListIndex[$v1['corp_type'].'-'.$v1['user_id']] ?? [];
                empty($oldDingtalkUser) && $oldDingtalkUser = $allDingUserListIndex[$v1['user_id']] ?? [];
                $userIdsArr[$v1['corp_type']] = $v1['userid'];
                $departmentsArr[] = $v1['department_id'] == 1 ? $v1['corp_type'] : $v1['department_id'];
                $mainCorpDeptIds[] = $v1['department_id'];
                if(empty($birthDay) && !empty($oldHrms[$v1['corp_type']]) && !empty($oldHrms[$v1['corp_type']]['birth_time'])){
                    $birthDay = date('m-d',strtotime($oldHrms[$v1['corp_type']]['birth_time']));
                }
            }
            if(!empty($oldHrms[$v1['corp_type']])){
                $mainDeptId = ($oldHrms[$v1['corp_type']]['main_dept_id'] == -1) ? 1 : $oldHrms[$v1['corp_type']]['main_dept_id'];
            }elseif(in_array(1,$mainCorpDeptIds)){
                $mainDeptId = 1;
            }else{
                $mainDeptId = $mainCorpDeptIds[0];
            }
            if($mainDeptId == 1){
                $mainDeptSubrootId = 1;
            }else{
                $mainDeptInfo = $allDepartmentListIndex[$mainDeptId]??[];
                $mainDeptSubrootId = $mainDeptInfo['subroot_id'] ?? 1;
            }
            $dingUserParams = [
                'kael_id'=>$oldDingtalkUser['kael_id']??0,
                'corp_type'=>$mainCorpType,
                'user_id'=>$mainUserId,
                'name'=>$mainUserInfo['name'],
                'mobile'=>$mobile,
                'avatar'=>$mainUserInfo['avatar'],
                'job_number'=>$mainUserInfo['job_number'],
                'union_id'=>$mainUserInfo['union_id'],
                'open_id'=>$mainUserInfo['open_id'],
                'departments'=>join(',',$departmentsArr),
                'department_id'=>$mainDeptId,
                'department_subroot'=>$mainDeptSubrootId,
                'hired_date'=>$hireDay,
                'birthday'=>$birthDay,
            ];
            if(empty($dingUserParams['kael_id'])){
                //没有kaelId
                $kaelUser = UserCenter::find()
                    ->where(['mobile' =>$mobile,'status'=>[0,1]])
                    ->orderBy('status asc,id desc')
                    ->limit(1)
                    ->asArray(true)
                    ->one();
                if (!empty($kaelUser)){
                    //有kaelid
                    $kaelParams = [];
                    $kaelUser['user_type'] != 0 && $kaelParams['user_type'] = 0;
                    $kaelUser['status'] !=0 && $kaelParams['status'] = 0;
                    $kaelUser['work_number'] != $dingUserParams['job_number'] && $kaelParams['work_number'] == $dingUserParams['job_number'];
                    $kaelUser['username'] != $dingUserParams['name'] && $kaelParams['username'] == $dingUserParams['name'];
                    !empty($kaelParams) && UserCenter::updateAll($kaelParams, ['id' => $kaelUser['id']]);
                    $kaelId = $kaelUser['id'];
                }else{
                    $kaelParams = [
                        'username' => $dingUserParams['name'],
                        'password' => md5('1!Aaaaaaa'),
                        'sex' => 1, //1男 2女
                        'work_number' => $dingUserParams['job_number'],
                        'mobile' => $userInfo['mobile'] ?? '',
                        'user_type' => 0,
                        'status'=>0
                    ];
                    $kaelId = UserCenter::addUser($kaelParams);
                }
                $dingUserParams['kael_id'] = $kaelId;

                if(!empty($oldDingtalkUser)){
                    unset($allDingUserListIndex[$oldDingtalkUser['user_id']]);
                    DingtalkUser::updateAll($dingUserParams,['user_id'=>$oldDingtalkUser['user_id']]);
                }else{
                    DingtalkUser::add($dingUserParams);
                }
            }
        }

    }



    public function hrmUserInfo($corpType){
        $allUserIds = array_values(array_unique(array_column(DingtalkDepartmentUser::findList(['corp_type'=>$corpType], '', 'user_id'),'user_id')));
        $allUserIdsChunkList = array_chunk($allUserIds,20);

        $allHrmUserList = DingtalkHrmUser::findList(['corp_type'=>$corpType],'','user_id,main_dept_id,id');
        $allHrmUserIndex = [];
        foreach ($allHrmUserList as $v){
            $allHrmUserIndex[$v['user_id']] = $v;
        }

        foreach ($allUserIdsChunkList as $userIdsChunkOne){
            if($corpType == 1){
                $resultList = DingTalkApi::getHrmUserInfoByUids($userIdsChunkOne);
            }elseif($corpType == 2){
                $resultList = DingTalkApiJZ::getHrmUserInfoByUids($userIdsChunkOne);
            }else{
                throw new Exception("不支持的corpType");
            }
            foreach ($resultList as $resultUser){
                echo json_encode($resultUser,64|256)."\n";
                $old = $allHrmUserIndex[$resultUser['userid']]??[];
                $userField = array_column($resultUser['field_list'],'label','field_code');
                if(empty($userField['sys00-mobile'])){
                    !empty($old) && DingtalkHrmUser::updateAll(['status'=>1],['id'=>$old['id']]);
                    continue;
                }
                if(substr($userField['sys00-mobile'],0,4) == '+86-'){
                    $userField['sys00-mobile'] = substr($userField['sys00-mobile'],4);
                }
                $updateParmas = [
                    'corp_type'=>$corpType,
                    'user_id'=>$resultUser['userid'],
                    'name'=>$userField['sys00-name'],
                    'mobile'=>$userField['sys00-mobile'],
                    'email'=>$userField['sys00-email']??'',
                    'job_number'=>$userField['sys00-jobNumber']??'',
                    'sex'=>$userField['sys02-sexType']??'',
                    'main_dept_id'=>$userField['sys00-mainDeptId']??'0',
                    'main_dept_name'=>$userField['sys00-mainDept']??'',
                    'employee_type'=>$userField['sys01-employeeType']??'',
                    'employee_status'=>$userField['sys01-employeeStatus']??'',
                    'birth_time'=>$userField['sys02-birthTime']??'',
                ];
                if(!empty($old)){
                    //update
                    DingtalkHrmUser::updateAll($updateParmas,['id'=>$old['id']]);
                }else{
                    //insert
                    DingtalkHrmUser::add($updateParmas);
                }
            }
            sleep(1);
        }

    }

    public function hrmUserDismiss($coryType){
        $oldUserIds = DingtalkHrmUserLeave::findList(['corp_type'=>$coryType],'','user_id');
        $oldUserIds = array_column($oldUserIds,'user_id');
        if($coryType == 1){
            $uids = DingTalkApi::getHrmLizhiUids();
            $uids = array_values(array_diff($uids,$oldUserIds));
            if(empty($uids)){
                echo "没有新增离职";
                return [];
            }
            $userInfoList = DingTalkApi::getHrmLizhiUserInfo($uids);
        }elseif($coryType == 2){
            $uids = DingTalkApiJZ::getHrmLizhiUids();
            $uids = array_values(array_diff($uids,$oldUserIds));
            if(empty($uids)){
                echo "没有新增离职";
                return [];
            }
            $userInfoList = DingTalkApiJZ::getHrmLizhiUserInfo($uids);
        }else{
            throw new Exception("不支持的类型");
        }
        foreach ($userInfoList as $v){
            echo json_encode($v,64|256)."\n";
            if(!in_array($v['status'],[1,2])){
                echo "=======================================\n";
                continue;
            }
            $params = [
                'corp_type'=>$coryType,
                'user_id'=>$v['userid'],
                'last_work_day'=>date('Y-m-d H:i:s',intval($v['last_work_day']/1000)),
                'reason_memo'=>$v['reason_memo']??'',
                'reason_type'=>$v['reason_type']??0,
                'pre_status'=>$v['pre_status'],
                'handover_userid'=>$v['handover_userid']??'',
                'ding_status'=>$v['status'],
                'main_dept_name'=>$v['main_dept_name']??'',
                'main_dept_id'=>$v['main_dept_id']??0,
            ];
            $columns = ['corp_type','user_id','dept_id','dept_path','is_main'];
            $rows = [];
            foreach ($v['dept_list']??[] as $vDept){
                $rows[$vDept['dept_id']] = [
                    $coryType,$v['userid'],
                    $vDept['dept_id'],$vDept['dept_path'],
                    $vDept['dept_id'] == $params['main_dept_id'] ? 1 : 0
                ];
            }
            try{
                $old = DingtalkHrmUserLeave::findOneByUid($v['userid'],$coryType);
                if(empty($old)){
                    DingtalkHrmUserLeave::add($params);
                    !empty($rows) && DingtalkHrmUserLeaveDept::batchInsertAll(
                        DingtalkHrmUserLeaveDept::tableName(),
                        $columns,
                        $rows,
                        DingtalkHrmUserLeaveDept::getDb()
                    );
                }
            }catch (\Exception $e){
                echo "===========ERR===================\n";
                echo $e->getMessage()."\n";
                continue;
            }

        }
    }


    public function actionHrm(){
        echo "同步2企业花名册\n";
        $this->hrmUserInfo(2);
        echo "同步1企业花名册\n";
        $this->hrmUserInfo(1);
        echo "同步2企业离职信息\n";
        $this->hrmUserDismiss(2);
        echo "同步1企业离职信息\n";
        $this->hrmUserDismiss(1);

    }

    public function actionAutoPriv(){
        //开通权限
        $allNeedPrivList = RelateDingtalkDepartmentPlatform::findAllList();
        $departmentPrivs = [];
        foreach ($allNeedPrivList as $v){
            $departmentPrivs[$v['department_id']][] = $v['platform_id'];
        }
        foreach ($departmentPrivs as $departmentId=>$platformIds){
            $platformIds = array_values(array_unique($platformIds));
            $departmentIdsInPath = DingtalkDepartment::findListByWhereAndWhereArr([],[['like','path_id',"|{$departmentId}|"]],'id');
            $departmentIdsInPath = array_column($departmentIdsInPath,'id');
            if(empty($departmentIdsInPath)){
                continue;
            }
            $allKaelIds = DingtalkDepartmentUser::findList(['department_id'=>$departmentIdsInPath],'','kael_id');
            $allKaelIds = array_values(array_unique($allKaelIds));
            foreach ($allKaelIds as $kaelId){
                if(empty($kaelId)){
                    continue;
                }
                $oldPlatIds = RelateUserPlatform::find()
                    ->select('platform_id')
                    ->where(['user_id'=>$kaelId,'status'=>0])->asArray(true)->column();
                $addPlat = array_diff($platformIds,$oldPlatIds);
                !empty($addPlat) && RelateUserPlatform::batchAdd($kaelId,$addPlat);
            }
        }
    }

}
