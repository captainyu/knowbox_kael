<?php
namespace common\models;

use Yii;

class DepartmentRelateToKael extends BaseActiveRecord
{


    public static function tableName()
    {
        return 'department_relate_to_kael';
    }

    public static function getDb()
    {
        return Yii::$app->get('db');
    }

    public static function findListByWhereAndWhereArr($where,$whereArr,$select='*'){
        !isset($where['status']) && $where['status'] = 0;
        $query =  self::find()->select($select)->where($where);
        foreach ($whereArr as $v){
            $query = $query->andWhere($v);
        }
        return $query->asArray(true)->all();
    }
    public static function findList($where=[],$indexKey="",$select='*',$status=0){
        !isset($where['status']) && $status != -1 && $where['status'] = $status;
        if(!empty($indexKey)){
            return static::find()
                ->select($select)
                ->where($where)
                ->indexBy($indexKey)
                ->asArray(true)
                ->all();
        }
        return static::find()
            ->select($select)
            ->where($where)
            ->asArray(true)
            ->all();
    }
    public static function findOneByWhere($where,$select='*',$order=""){
        !isset($where['status']) && $where['status'] = 0;
        $query = static::find()
            ->select($select)
            ->where($where);
        !empty($order) && $query = $query->orderBy($order);
        return $query
            ->limit(1)
            ->asArray(true)
            ->one();
    }

}
