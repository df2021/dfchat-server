<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2019 广东卓锐软件有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------

namespace app\member\model;

use think\Model;
use think\helper\Hash;

/**
 * 后台用户模型
 * @package app\admin\model
 */
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

    //获取器
    public function getLastLoginIpAttr($value)
    {
        return long2ip($value);
//        $ip_info = IPLoc::find($ip);
//        $city = implode('',$ip_info);
        //dump($ip_info);
    }

    /*public function getLocationAttr($value,$data)
    {
        $ip = long2ip($data['last_login_ip']);
        return ipCity($ip);
    }*/

}
