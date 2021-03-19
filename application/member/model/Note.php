<?php


namespace app\member\model;


use think\Model;

class Note extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'note';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
}