<?php


namespace app\api\model;


use think\helper\Hash;
use think\Model;

class Member extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'member';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 对密码进行加密
    public function setPasswordAttr($value)
    {
        return Hash::make((string)$value);
    }

    // 获取注册ip
    public function setSignupIpAttr()
    {
        return get_client_ip();
    }
}