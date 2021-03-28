<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2019 广东卓锐软件有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------

namespace app\member\admin;

use app\admin\controller\Admin;
use app\admin\model\Attachment;
use app\common\builder\ZBuilder;
use app\member\model\Member as MemberModel;
use think\Db;
use think\facade\Cache;
use think\facade\Hook;
use think\Request;

/**
 * 消息控制器
 * @package app\user\admin
 */
class Index extends Admin
{
    /**
     * 消息列表
     * @author 蔡伟明 <314013107@qq.com>
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function index()
    {

        $data_list = MemberModel::where($this->getMap())
            ->order($this->getOrder('is_kefu desc,id desc,last_login_time'))
            ->paginate();

        $columns = [
            ['id', 'ID'],
            ['username', '用户名'],
            ['nickname', '昵称'],
            ['role', '会员类型'],
            //['balance', '余额'],
            //['freeze_balance', '待提本佣金额'],
            ['last_login_ip', '最近登录IP'],
            ['location', '所在地'],
            ['last_login_time', '最近登录时间', 'datetime'],
            ['create_time', '创建时间', 'datetime'],
        ];
        $auth = model('user/role')->roleAuth();
        $role = session('user_auth.role');
        if(in_array('member/index/quickedit',$auth) || $role==1){
            $columns[] = ['status', '状态', 'switch'];
        }
        $columns[] = ['right_button', '操作', 'btn'];
        return ZBuilder::make('table')
            ->addOrder('id,last_login_time')
            ->setPageTitle('会员管理') // 设置页面标题
            ->setTableName('member')
            ->setSearch(['id' => 'ID', 'username' => '用户名']) // 设置搜索参数
            ->addColumns($columns)
            //->setColumnWidth('last_login_ip', 180)
            ->addTopButtons('add,enable,disable,delete') // 批量添加顶部按钮
            ->addRightButton('edit')
            ->addRightButton('delete')
            ->addFilter('status', ['启用', '禁用'])
            ->setRowList($data_list)
            ->fetch();
    }

    public function add()
    {
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data['avatar'] = '/static/image/boy.jpg';
            if(isset($data['is_jianguan']) && ($data['is_jianguan']==1)){
                $data['avatar'] = \think\facade\Request::domain() .'/uploads/images/weiquan.jpg';
            }
            if(isset($data['is_kefu']) && ($data['is_kefu']==1)){
                $data['avatar'] = '/static/logo.png';
            }

            if ($user = MemberModel::create($data)) {
                //自动添加客服
                $uid = $user['id'];
                Db::startTrans();
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
                    autoAddFriend($uid,$item['id'],$item['is_jianguan']);
                }
                Db::commit();
                // 记录行为
//                action_log('member_add', 'member', $user['id'], UID);
                $this->success('新增成功', url('index'));
            } else {
                $this->error('新增失败');
            }
        }


        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('新增') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['text', 'username', '用户名', '必填，可由英文字母、数字组成'],
                ['text', 'nickname', '昵称', '可以是中文'],
//                ['select', 'role', '角色', '非超级管理员，禁止创建与当前角色同级的用户', $role_list],
//                ['text', 'email', '邮箱', ''],
                ['password', 'password', '密码', '必填，6-20位'],
//                ['text', 'mobile', '手机号'],
//                ['image', 'avatar', '头像'],
                ['radio', 'is_kefu', '客服','',[0=>'否',1=>'是'],0],
                ['radio', 'is_jianguan', '维权监管','',[0=>'否',1=>'是'],0],
//                ['radio', 'status', '状态', '', ['禁用', '启用'], 1]
            ])
            ->fetch();
    }

    public function edit($id = null)
    {
        if ($id === null) $this->error('缺少参数');
        $basepath = 'http://'.$_SERVER['HTTP_HOST'];
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if($data['is_jianguan']==1){
                $role = session('user_auth.role');
                if($role!=1){
                    $this->error('权限不足');
                }
            }
            // 如果没有填写密码，则不更新密码
            if ($data['password'] == '') {
                unset($data['password']);
            }
            //如果更换图片
            if($data['avatar']!=''){
                $file = new Attachment();
                $data['avatar'] = $basepath.$file->getFilePath($data['avatar']);
            }
            if (MemberModel::update($data)) {
                $user = MemberModel::get($data['id']);
                // 记录行为
                action_log('member_edit', 'member', $user['id'], UID, get_nickname($user['id']));
                $this->success('编辑成功', 'index');
            } else {
                $this->error('编辑失败');
            }
        }
        // 获取数据
        $info = MemberModel::where('id', $id)->field('password', true)->find();
        //查找附件中的值
        $attachPath = str_replace($basepath.'/','',$info['avatar']);
        $attachmentId = Db::table('df_admin_attachment')->where('path',$attachPath)->value('id');
        $info['avatar'] = $attachmentId;
        //dump($attachmentId);
        //dump($info);
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('编辑') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['hidden', 'id'],
                ['static', 'username', '用户名', '不可更改'],
                ['image', 'avatar', '头像'],
                ['text', 'nickname', '昵称', '可以是中文'],
                ['text', 'signature', '个性签名'],
                ['password', 'password', '密码', '必填，6-20位'],
                ['radio', 'is_kefu', '客服','',[0=>'否',1=>'是']],
                ['radio', 'is_jianguan', '维权监管','',[0=>'否',1=>'是']],
                ['radio', 'status', '状态', '', ['禁用', '启用']]
            ])
            ->setFormData($info) // 设置表单数据
            ->fetch();
    }

    public function delete($ids = [])
    {
        Hook::listen('member_delete', $ids);
        return $this->setStatus('delete');
    }

    public function setStatus($type = '', $record = [])
    {
        $ids   = $this->request->isPost() ? input('post.ids/a') : input('param.ids');
        $ids   = (array)$ids;
        $field = input('param.field', 'status');

        empty($ids) && $this->error('缺少主键');

        $Model = $this->getCurrModel();
        $protect_table = [
            '__ADMIN_USER__',
            '__ADMIN_ROLE__',
            '__ADMIN_MODULE__',
            config('database.prefix').'admin_user',
            config('database.prefix').'admin_role',
            config('database.prefix').'admin_module',
        ];

        // 禁止操作核心表的主要数据
        if (in_array($Model->getTable(), $protect_table) && in_array('1', $ids)) {
            $this->error('禁止操作');
        }

        // 主键名称
        $pk = $Model->getPk();
        $map = [
            [$pk, 'in', $ids]
        ];

        $result = false;
        switch ($type) {
            case 'disable': // 禁用
                $result = $Model->where($map)->setField($field, 0);
                break;
            case 'enable': // 启用
                $result = $Model->where($map)->setField($field, 1);
                break;
            case 'delete': // 删除
                $groupIds = Db::table('df_group')->where('created_mid','in',$ids)->column('id');
                foreach ($groupIds as $groupId){ //删除相关群聊记录
                    Db::table('df_message_group')->where('group_id',$groupId)->delete();
                }
                Db::table('df_message_group')->where('send_mid','in',$ids)->delete();
                //删除其创建的群
                Db::table('df_group')->where('created_mid','in',$ids)->delete();
                //删除其相关好友
                Db::table('df_friends')->where('mid|friend_mid','in',$ids)->delete();
                //删除相关申请验证记录
                Db::table('df_apply')->where('send_mid|to_mid','in',$ids)->delete();
                //删除相关私聊消息
                Db::table('df_message')->where('send_mid|to_mid','in',$ids)->delete();
                $result = $Model->where($map)->delete();
                break;
            default:
                $this->error('非法操作');
                break;
        }

        if (false !== $result) {
            Cache::clear();
            // 记录行为日志
            if (!empty($record)) {
                call_user_func_array('action_log', $record);
            }
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }

}
