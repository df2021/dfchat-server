<?php


namespace app\member\model;


use think\Model;

class Group extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'group';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    public function getCreatedMidAttr($value)
    {
        $creator = Member::where('id',$value)->find();
        return !empty($creator['nickname']) ? $creator['nickname'] : $creator['username'];
    }

    public function getMembersAttr($value)
    {
        $list = explode(',',$value);
        return count($list);
    }
}