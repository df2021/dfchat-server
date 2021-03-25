<?php


namespace app\index\controller;


use think\Controller;

class User extends Controller
{
    public function login()
    {
        /*if($this->request->isPost()){
            $param = $this->request->post();
            $data = [
                'username'=>$param['username'],
                'password'=>(string)$param['pwd'],
                '__token__'    => $param['token'],
            ];
            // 验证数据
            $validate = new MemberValidate;
            $verification = $validate->scene('login')->check($data);
            if(true!==$verification){
                return json(['code'=>-1, 'info'=>$validate->getError()]);
            }
            $member = new MemberModel();
            $login_id = $member->login($data['username'],$data['password']);
            if($login_id===false){
                return json(['code'=>-1, 'info'=>$member->getError()]);
            }
            return json(['code'=>0, 'info'=>'登录成功']);
        }
        $token = $this->request->token('__token__', 'sha1');
        $this->assign('token', $token);*/
        return $this->fetch();
    }

}