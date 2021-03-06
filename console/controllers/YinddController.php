<?php
namespace console\controllers;

use common\libs\ydd\Ydd;
use common\models\DingtalkDepartment;
use common\models\DingtalkUser;
use Yii;
use yii\console\Controller;


class YinddController extends Controller
{
    /**
     * 初始化用户信息到Meican
     */
    public function actionUpdate(){
        if(exec('ps -ef|grep "yindd/update"|grep -v grep | grep -v cd | grep -v "/bin/sh"  |wc -l') > 1){
            echo "is_running";
            exit();
        }
        if(empty(Yii::$app->params['ydd_appkey'])){
            echo "未设置印点点信息\n";
            exit();
        }
        echo date("Y-m-d H:i:s ")."=========更新打印机信息========\n";
        //全部部门
        $yinddDepartmentList = Ydd::depList();
        if(false === $yinddDepartmentList){
            exit();
        }
        $yinddDepamentNameToId = array_column($yinddDepartmentList,'id','name');
        //全部用户
        $yinddUserList = Ydd::userList(1,5000);
        if(false === $yinddUserList){
            exit();
        }
        $yinddUserList = array_column($yinddUserList,null,'account');
        $yinddUserListEmail = array_column($yinddUserList,null,'email');
        $dingtalkUserList = DingtalkUser::findList(['corp_type'=>1],'','auto_id,ydd_account,department_subroot,email,name');
        $dingtalkDepartmentIdToName = array_column(DingtalkDepartment::findList(['parentid'=>1,'status'=>0],'','id,name'),'name','id');
        //更新用户
        foreach ($dingtalkUserList as $v){
            if(empty($v['email'])){
                continue;
            }
            //department
            if($v['department_subroot'] == 1){
                $departmentName = '合伙人';
            }else{
                $departmentName = $dingtalkDepartmentIdToName[$v['department_subroot']] ?? '未知';
            }
            if(empty($yinddDepamentNameToId[$departmentName])){
                $newDep = Ydd::depAdd($departmentName);
                if(false === $newDep){
                    exit();
                }
                if(empty($newDep['id'])){
                    echo "空id\n";
                    exit();
                }
                $yinddDepamentNameToId[$newDep['name']] = $newDep['id'];
            }
            if(empty($yinddUserList[$v['ydd_account']])){
                if(!empty($yinddUserListEmail[$v['email']])){
                    echo "fromEmail: {$v['name']} {$v['email']} \n";
                    $yddUserInfo = $yinddUserListEmail[$v['email']];
                    DingtalkUser::updateAll(['ydd_account'=>$yddUserInfo['account']],['auto_id'=>$v['auto_id']]);
                    unset($yinddUserList[$yddUserInfo['account']]);
                }else{
                    //无邮箱
                    echo "addUser: {$v['name']} {$v['email']} \n";
                    $yddAccountId = Ydd::userAdd($v['name'],$v['email'],$yinddDepamentNameToId[$departmentName]);
                    if(false === $yddAccountId){
                        exit();
                    }
                    DingtalkUser::updateAll(['ydd_account'=>$yddAccountId],['auto_id'=>$v['auto_id']]);
                }

            }else{
                $yddUserInfo = $yinddUserList[$v['ydd_account']];
                if($yddUserInfo['name']!=$v['name'] || $yddUserInfo['email'] != $v['email']
                    || !empty($yddUserInfo['phone'])){
                    echo "updateUser: {$v['name']} {$v['email']} \n";
                    Ydd::userUpdate($v['ydd_account'],$v['name'],$v['email'],'',$yinddDepamentNameToId[$departmentName]);
                }
                unset($yinddUserList[$v['ydd_account']]);
            }
        }
        foreach ($yinddUserList as $v){
            echo "del: ".json_encode($v,64|256)."\n";
            Ydd::userDel($v['account']);
        }
    }

    public function actionTest(){
        $ret = Ydd::depAdd('测试部门');
        echo json_encode($ret,JSON_UNESCAPED_SLASHES);
        $ret = Ydd::userAdd('测试2','test2@knowbox.cn',8396);
        echo "userAdd: ".strval($ret)."\n";
        $ret = Ydd::userDel('1463458196');
        echo "userDel: ".strval($ret)."\n";
    }

    public function actionInitUser(){
        $yinddUserList = Ydd::userList();
        if(false === $yinddUserList){
            exit();
        }
        //更新用户
        foreach ($yinddUserList as $v){
            /**
            [{
            "account": "1928581282",
            "name": "刘夜",
            "sex": 1,
            "phone": "13911552662",
            "telephone": null,
            "birthday": null,
            "companyId": "105383",
            "department_Id": 8123,
            "departmentName": "合伙人",
            "cardNo": "",
            "email": "liuye@knowbox.cn",
            "colorAuth": 2,
            "printAuth": 0
            }]
             */
            echo json_encode($v,64|256)."\n";
            DingtalkUser::updateAll(['ydd_account'=>$v['account']],['mobile'=>$v['phone'],'status'=>0]);
        }
    }


}
