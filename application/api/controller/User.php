<?php


namespace app\api\controller;


use app\api\model\Member as MemberModel;
use app\api\validate\Member;
use think\captcha\Captcha;
use think\Db;
use think\facade\Request;
use think\helper\Hash;

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
            // 是否画混淆曲线
            'useCurve'    =>    false,
            'imageW'    =>    90,
            'imageH'    =>    33,
        ];
        $captcha = new Captcha($config);
        $captcha->fontttf = '4.ttf';
        return $captcha->entry($token);
    }

    public function resetPassword()
    {
        if($this->request->isPost()){
            $param = $this->request->post();
            $data = [
                'username'  => $param['username'],
                'password'  => $param['password'],
                'newPassword'  => $param['newPassword'],
            ];
            //验证
            $validate = new Member();
            $verification = $validate->scene('login')->check($data);
            if(true!==$verification){
                return json(['code'=>-1, 'error'=>$validate->getError()]);
            }
            $member = new MemberModel();
            $member_password = $member->where('username',$data['username'])->where('status',1)->value('password');
            if(!Hash::check($data['password'],$member_password)){
                return json(['code'=>-1, 'error'=>'原密码输入错误']);
            }
            $newPassword = Hash::make($data['newPassword']);
            $member_info = $member->where('username',$data['username'])->setField('password',$newPassword);
            if(!$member_info){
                return json(['code'=>-1, 'error'=>$member->getError()]);
            }
            return json([
                'code'=>0,
                'msg'=>'修改成功'
            ]);
        }
        return null;
    }

    public function login()
    {
        if($this->request->isPost()){
            $param = $this->request->post();
            $data = [
                'username'  => $param['username'],
                'password'  => $param['password'],
            ];
            //验证
            $validate = new Member();
            $verification = $validate->scene('login')->check($data);
            if(true!==$verification){
                return json(['code'=>-1, 'error'=>$validate->getError()]);
            }
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
            //验证
            $validate = new Member();
            $verification = $validate->check($data);
            if(true!==$verification){
                return json(['code'=>-1, 'error'=>$validate->getError()]);
            }
            //添加默认头像
            $headImg = [
                '/static/img/im/face/face_1.jpg',
                '/static/img/im/face/face_2.jpg',
                '/static/img/im/face/face_3.jpg',
                '/static/img/im/face/face_4.jpg',
                '/static/img/im/face/face_5.jpg',
                '/static/img/im/face/face_6.jpg',
                '/static/img/im/face/face_7.jpg',
                '/static/img/im/face/face_8.jpg',
                '/static/img/im/face/face_9.jpg',
                '/static/img/im/face/face_10.jpg',
                '/static/img/im/face/face_11.jpg',
                '/static/img/im/face/face_12.jpg',
                '/static/img/im/face/face_13.jpg',
                '/static/img/im/face/face_14.jpg',
                '/static/img/im/face/face_15.jpg',
                '/static/image/boy.jpg',
                '/static/image/girl.jpg',
                '/static/image/guanxi.jpg',
                '/static/image/huge.jpg'
            ];
            $rand_keys = array_rand($headImg, 1);
            $data['avatar'] = Request::domain().'/uploads'.$headImg[$rand_keys];
//            $data['avatar'] = '/static/image/boy.jpg';
            $memberModel = model('member');
            if(true!==$memberModel->save($data)){
                return json(['code'=>-1, 'error'=>$memberModel->getError()]);
            }
            //自动添加客服
            $uid = Db::table('df_member')->where('username',$data['username'])->value('id');
            $kefuList = Db::table('df_member')->field('id,is_kefu,is_jianguan')->where('is_kefu|is_jianguan','=',1)->select();

            $welcome = Db::table('df_system_config')->order('id','desc')->value('welcome');
            foreach ($kefuList as $item){
                if(!empty($welcome) && ($item['is_jianguan']==1)){
                    $time = time();
                    Db::table('df_message')->insert([
                        'send_mid'=>$item['id'],
                        'to_mid'=>$uid,
                        'type'=>1,
                        'status'=>0,
                        'content'=>$welcome,
                        'create_time'=>$time,
                        'update_time'=>$time,
                        'send_time'=>$time,
                    ]);
                }
                //$this->autoAddFriend($uid,$item['id']);
                autoAddFriend($uid,$item['id'],$item['is_jianguan']);
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

    public function getMemberInfo()
    {
        if($this->request->post()){
            $param = $this->request->post();
            $mid = $param['mid'];
            $list = Db::table('df_member')->where('id',$mid)
                ->field('id,username,nickname,signature,avatar')->find();
            if(!empty($list)){
                return json([
                    'code'=>0,
                    'data'=>$list
                ]);
            }else{
                return json(['code'=>-1, 'error'=>'没有查询到该用户信息']);
            }
        }
    }
}