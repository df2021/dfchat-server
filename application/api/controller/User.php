<?php


namespace app\api\controller;


use app\api\model\Member as MemberModel;
use think\captcha\Captcha;

class User extends Index
{
    /*public function encryptToken()
    {
        $content = '{"username":"zhangshan","id":1}';
        return encryptToken($content);
    }*/

    public function decryptToken()
    {
        $s = $this->request->get('str');
        if(!empty($s)){
            return decryptToken($s);
        }
        return null;
    }

    public function getToken()
    {
        if($this->request->isGet()){
            $token = $this->request->token('__token__', 'sha1');
            return json(['code'=>0, 'data'=>[],'token'=>$token]);
        }
        return null;
    }

    public function refreshToken($refreshToken)
    {
        $data = decryptToken($refreshToken);
        $data = json_decode($data,true);
        $mid = MemberModel::where('id',$data['id'])->value('id');
        if($mid>0){
            $token = json_encode(['mid'=>$mid,'getTime'=>request()->time()]);
            return encryptToken($token);
        }
        return null;
    }

    public function verifyCode($token='')
    {
        $config =    [
            // 验证码字体大小
            'fontSize'    =>    13,
            // 验证码位数
            'length'      =>    4,
            // 关闭验证码杂点
            'useNoise'    =>    false,
            'imageW'    =>    90,
            'imageH'    =>    33,
        ];
        $captcha = new Captcha($config);
        $captcha->fontttf = '5.ttf';
        return $captcha->entry($token);
    }

    public function login()
    {
        if($this->request->isPost()){
            $param = $this->request->post();
            $data = [
                'username'  => $param['username'],
                'password'  => $param['password'],
            ];
            $member = new MemberModel();
            $member_info = $member->login($data['username'],$data['password']);
            if($member_info===false){
                return json(['code'=>-1, 'error'=>$member->getError()]);
            }
            $data = json_encode(['mid'=>$member_info['id'],'getTime'=>request()->time()]);
            $token = encryptToken($data);
            return json([
                'code'=>0,
                'data'=>$member_info,
                'msg'=>'登录成功',
                'token'=>$token
            ]);
        }
        return null;
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
            if(!$captcha->checkApi($data['verify'],$param['token'])){
                return json(['code'=>-1, 'error'=>'验证码错误']);
            }

            if(true!==model('member')->save($data)){
                return json(['code'=>-1, 'error'=>'数据错误，注册失败']);
            }

            //自动登录
            $member = new MemberModel();
            $member_info = $member->login($data['username'],$data['password']);
            if($member_info===false){
                return json(['code'=>-1, 'error'=>$member->getError()]);
            }
            $data = json_encode(['mid'=>$member_info['id'],'getTime'=>request()->time()]);
            $token = encryptToken($data);
            return json([
                'code'=>0,
                'data'=>$member_info,
                'msg'=>'注册成功',
                'token'=>$token
            ]);
        }
        return null;
    }

    public function logout()
    {

    }
}