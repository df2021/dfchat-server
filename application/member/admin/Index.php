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
use app\common\builder\ZBuilder;
use app\member\model\Member as MemberModel;
use think\facade\Hook;

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
            ->order($this->getOrder('id desc,last_login_time'))
            ->paginate();

        return ZBuilder::make('table')
            ->addOrder('id,last_login_time')
            ->setPageTitle('会员管理') // 设置页面标题
            ->setTableName('member')
            ->setSearch(['id' => 'ID', 'username' => '用户名']) // 设置搜索参数
            ->addColumns([
                ['id', 'ID'],
                ['username', '用户名'],
                //['balance', '余额'],
                //['freeze_balance', '待提本佣金额'],
                //['last_login_ip', '最近登录IP'],
                //['location', '所在地'],
                ['last_login_time', '最近登录时间', 'datetime'],
                ['create_time', '创建时间', 'datetime'],
                ['status', '状态', 'switch'],
                ['right_button', '操作', 'btn']
            ])
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
            if ($user = MemberModel::create($data)) {
                // 记录行为
                action_log('member_add', 'member', $user['id'], UID);
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
//                ['text', 'nickname', '昵称', '可以是中文'],
//                ['select', 'role', '角色', '非超级管理员，禁止创建与当前角色同级的用户', $role_list],
//                ['text', 'email', '邮箱', ''],
                ['password', 'password', '密码', '必填，6-20位'],
//                ['text', 'mobile', '手机号'],
//                ['image', 'avatar', '头像'],
                ['radio', 'status', '状态', '', ['禁用', '启用'], 1]
            ])
            ->fetch();
    }

    public function edit($id = null)
    {
        if ($id === null) $this->error('缺少参数');

        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

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
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('编辑') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['hidden', 'id'],
                ['static', 'username', '用户名', '不可更改'],
//                ['static', 'usdt_address', 'USDT地址', '不可更改'],
                ['password', 'password', '密码', '必填，6-20位'],
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

}
