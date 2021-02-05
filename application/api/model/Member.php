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

    /**
     * 用户登录
     * @param string $username 用户名
     * @param string $password 密码
     * @return bool|mixed
     */
    public function login($username = '', $password = '')
    {
        $username = trim($username);
        $password = trim($password);

        // 匹配登录方式
        /*if (preg_match("/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/", $username)) {
            // 邮箱登录
            $map['email'] = $username;
        } elseif (preg_match("/^1\d{10}$/", $username)) {
            // 手机号登录
            $map['mobile'] = $username;
        } else {
            // 用户名登录
            $map['username'] = $username;
        }*/
        $map['username'] = $username;
        $map['status'] = 1;

        // 查找用户
        $user = $this::get($map);
        if (!$user) {
            $this->error = '用户不存在或被禁用！';
        } else {

            if (!Hash::check($password, $user['password'])) {
                $this->error = '账号或者密码错误！';
            } else {
                //$uid = $user['id'];
                // 更新登录信息
                $user['last_login_time'] = request()->time();
                $user['last_login_ip']   = request()->ip(1);
                if ($user->save()) {
                    // 自动登录
                    return $user;
                } else {
                    // 更新登录信息失败
                    $this->error = '登录信息更新失败，请重新登录！';
                    return false;
                }
            }
        }
        return false;
    }

}