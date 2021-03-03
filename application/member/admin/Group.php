<?php


namespace app\member\admin;


use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\member\model\Group as GroupModel;
use app\member\model\messageGroup;
use think\Db;
use think\facade\Cache;

class Group extends Admin
{
    public function index()
    {
        $data_list = GroupModel::where($this->getMap())
            ->order($this->getOrder('id desc'))
            ->paginate();

        return ZBuilder::make('table')
            ->setPageTitle('群组管理') // 设置页面标题
            ->setTableName('group')
            ->setSearch(['name' => '名称']) // 设置搜索参数
            ->addColumns([
                ['id', 'ID'],
                ['name', '名称'],
                ['icon', '图标','img_url'],
                ['created_mid', '群主'],
                ['members', '成员'],
                ['create_time', '创建时间', 'datetime'],
                ['update_time', '更新时间', 'datetime'],
                ['right_button', '操作', 'btn']
            ])
            //->setColumnWidth('last_login_ip', 180)
            ->addRightButton('edit')
            //->addRightButton('delete')
            ->setRowList($data_list)
            ->fetch();
    }

    public function message()
    {
        $data_list = messageGroup::where($this->getMap())
            ->order($this->getOrder('id desc'))
            ->paginate();

        return ZBuilder::make('table')
            ->setPageTitle('聊天记录') // 设置页面标题
            ->setTableName('message_group')
            //->setSearch(['name' => '名称']) // 设置搜索参数
            ->addColumns([
                ['id', 'ID'],
                ['group_id', '所在群'],
                ['send_mid', '发送人'],
                ['type','类型', [1 =>'文本',2 => '语音',3 => '图片',5=>'视频']],
                ['content', '内容'],
                ['create_time', '发送时间', 'datetime'],
                ['right_button', '操作', 'btn']
            ])
            //->setColumnWidth('last_login_ip', 180)
            //->addRightButton('edit')
            ->addRightButton('delete')
            ->setRowList($data_list)
            ->fetch();
    }

    public function edit($id = null)
    {
        if ($id === null) $this->error('缺少参数');

        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if(is_array($data['manage'])){
                $data['manage'] = implode(',',$data['manage']);
            }
            if (GroupModel::update($data)) {
                $this->success('编辑成功', 'index');
            } else {
                $this->error('编辑失败');
            }
        }
        // 获取数据
        $info = Db::table('df_group')->where('id', $id)->find();
        $member_arr = explode(',',$info['members']);
        $memberList = [];
        $members = Db::table('df_member')
            ->where('id','in',$member_arr)
            ->order('id','desc')
            ->column('username,nickname','id');
        foreach ($members as $id => $item){
            $memberList[$id] = $item['username'].'-'.$item['nickname'];
        }
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('编辑') // 设置页面标题
            ->addFormItems([
                ['hidden', 'id'],
                ['static', 'name', '名称'],
                ['select', 'manage', '管理员[:请选择群内成员]','',$memberList, '', 'multiple'],
            ])
            ->setFormData($info) // 设置表单数据
            ->fetch();
    }

    /*public function delete($record = [])
    {
        return $this->setStatus('soft_delete',$record);
    }*/

    /**
     * 设置状态
     * 禁用、启用、删除都是调用这个内部方法
     * @param string $type 操作类型：enable,disable,delete
     * @param array $record 行为日志内容
     * @author 蔡伟明 <314013107@qq.com>
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
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
                $result = $Model->where($map)->delete();
                break;
            case 'soft_delete': // 软删除
                $result = $Model->where($map)->setField($field, -1);
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