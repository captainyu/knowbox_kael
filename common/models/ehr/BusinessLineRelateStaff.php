<?php
namespace common\models\ehr;

use Yii;

class BusinessLineRelateStaff extends \common\models\BaseActiveRecord
{

    public static function tableName()
    {
        return 'business_line_relate_staff';
    }

    public static function getDb()
    {
        return Yii::$app->get('db_ehr');
    }

    public static function findOneByWhere($where,$select='*',$order="",$status=0){
        !isset($where['status']) && $status != -1 && $where['status'] = 0;
        $query = static::find()
            ->select($select)
            ->where($where);
        !empty($order) && $query = $query->orderBy($order);
        return $query
            ->limit(1)
            ->asArray(true)
            ->one();
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

}
