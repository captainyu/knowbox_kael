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
        $ret = Ydd::depList();
        $retJson = json_decode($ret,true);
        if(empty($retJson) || !isset($retJson['status']) || $retJson['status'] != 8000){
            echo "=========获取部门列表失败========\n";
            echo strval($ret)  . "\n";
            exit();
        }
        /**
         * [{
        "id": 8123,
        "parentId": null,
        "name": "合伙人",
        "level": 0
        }]
         */
        $yinddDepartmentList = $retJson['data'];
        $yinddDepamentNameToId = array_column($yinddDepartmentList,'id','name');
        //全部用户
        $ret = Ydd::userList(1,5000);
        $retJson = json_decode($ret,true);
        if(empty($retJson) || !isset($retJson['status']) || $retJson['status'] != 8000){
            echo "========获取用户列表失败========\n";
            echo strval($ret)  . "\n";
            exit();
        }
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
        $yinddUserList = array_column($retJson['data'],null,'account');
        $dingtalkUserList = DingtalkUser::findList([],'','auto_id,ydd_account,department_subroot,email,name');
        $dingtalkDepartmentIdToName = array_column(DingtalkDepartment::findList(['parentid'=>1,'status'=>0],'','id,name'),'name','id')
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
                Ydd::depAdd($departmentName);
            }
            if(empty($yinddUserList[$v['ydd_account']])){
//                Ydd::userAdd($v['name'],$v['email'],)
            }else{

            }

        }
    }

    public function actionTest(){
        $ret = Ydd::depAdd('测试部门');
        echo "depAdd: ".$ret."\n";
    }

    public function actionInitUser(){
        $ret = Ydd::userList(1,5000);
        $retJson = json_decode($ret,true);
        if(empty($retJson) || !isset($retJson['status']) || $retJson['status'] != 8000){
            echo "========获取用户列表失败========\n";
            echo strval($ret)  . "\n";
            exit();
        }
        $yinddUserList = $retJson['data'];
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
            DingtalkUser::updateAll(['ydd_account'=>$v['account']],['mobile'=>$v['phone'],'status'=>0]);
        }
    }


}