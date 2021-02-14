<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2019 广东卓锐软件有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------

namespace app\api\validate;

use think\Validate;

/**
 * 用户验证器
 * @package app\admin\validate
 * @author 蔡伟明 <314013107@qq.com>
 */
class Member extends Validate
{
    // 定义验证规则
    protected $rule = [
        'username|用户名' => 'require|alphaNum|unique:member|length:5,25',
        'password|密码'  => 'require|length:6,20',
    ];

    // 定义验证提示
    protected $message = [
        'username.require' => '请输入用户名',
        'username.unique'  => '用户名已存在',
        'username.length'  => '用户名长度5-25位',
        'email.require'    => '邮箱不能为空',
        'email.email'      => '邮箱格式不正确',
        'email.unique'     => '该邮箱已存在',
        'password.require' => '密码不能为空',
        'password.length'  => '密码长度6-20位',
        'mobile.regex'     => '手机号不正确',
        '__token__.token'  => '令牌数据无效，请刷新页面',
    ];

    // login 验证场景定义
    public function sceneLogin()
    {
        return $this->only(['username','password'])
            ->remove('username', 'unique')
//            ->append('name', 'min:5')
            ->remove('password', 'require');
    }
    // 定义验证场景
    /*protected $scene = [
        //更新
        'update'  =>  ['email', 'password' => 'length:6,20', 'mobile', 'role', '__token__'],
        //登录
        'sign_in'  =>  ['username' => 'length:5,25', 'password' => 'require|length:6,20'],
    ];*/
}
