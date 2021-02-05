<?php


namespace app\api\controller;


use think\captcha\Captcha;

class User extends Index
{
    public function verifyCode()
    {
        $config =    [
            // 验证码字体大小
            'fontSize'    =>    14,
            // 验证码位数
            'length'      =>    4,
            // 关闭验证码杂点
            'useNoise'    =>    false,
            'imageW'    =>    90,
            'imageH'    =>    33,
        ];
        $captcha = new Captcha($config);

        return $captcha->entry('reg');
    }

    public function register()
    {
        if($this->request->isPost()){
            $param = $this->request->post();
            $data = [
                'username'  => $param['username'],
                'password'  => $param['password'],
                'verify'    => $param['verifyCode'],
            ];
            $captcha = new Captcha();
            if(!$captcha->checkApi($data['verify'],'reg')){
                return json(['code'=>-1, 'error'=>'验证码错误']);
            }

            if(true!==model('member')->save($data)){
                return json(['code'=>-1, 'error'=>'数据错误，注册失败']);
            }

            //自动登录
            /*$member = new MemberModel();
            $login_id = $member->login($data['username'],$data['password']);
            if($login_id===false){
                return json(['code'=>-1, 'info'=>$member->getError()]);
            }*/
            return json(['code'=>0, 'data'=>'注册成功']);
        }
        return null;
    }
}