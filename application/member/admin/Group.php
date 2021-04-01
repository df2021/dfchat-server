<?php


namespace app\member\admin;


use app\admin\controller\Admin;
use app\admin\model\Attachment;
use app\common\builder\ZBuilder;
use app\member\model\Group as GroupModel;
use app\member\model\messageGroup;
use app\member\model\KeywordsMask as KeywordsMaskModel;
use think\Db;
use think\facade\Cache;

class Group extends Admin
{
    public function index()
    {
        $data_list = GroupModel::where($this->getMap())
            ->order($this->getOrder('id desc'))
            ->paginate();

        $columns = [
            ['id', 'ID'],
            ['name', '名称'],
            ['icon', '图标','img_url'],
            ['created_mid', '群主'],
            ['members', '成员数量'],
        ];
        $auth = model('user/role')->roleAuth();
        $role = session('user_auth.role');
        if(in_array('member/group/quickedit',$auth) || $role==1){
            $columns[] = ['is_recommend', '是否推荐','switch'];
        }
        $columns[] = ['create_time', '创建时间', 'datetime'];
        $columns[] = ['update_time', '更新时间', 'datetime'];
        $columns[] = ['right_button', '操作', 'btn'];
        return ZBuilder::make('table')
            ->setPageTitle('群组管理') // 设置页面标题
            ->setTableName('group')
            ->setSearch(['name' => '名称']) // 设置搜索参数
            ->addColumns($columns)
            //->setColumnWidth('last_login_ip', 180)
            ->addRightButton('edit')
            ->addRightButton('delete')
            ->setRowList($data_list)
            ->fetch();
    }

    public function message()
    {
        $map = $this->getMap();
        //dump($map);
        foreach ($map as $k=>$item){
            if($item[0]=='send_mid'){
                $map[$k][2] = Db::table('df_member')->where('username|nickname',$item[2])->value('id');
            }
        }
        $data_list = messageGroup::where($map)
            ->order($this->getOrder('id desc'))
            ->paginate();

        return ZBuilder::make('table')
            ->setPageTitle('聊天记录') // 设置页面标题
            ->setTableName('message_group')
            //->setSearch(['name' => '名称']) // 设置搜索参数
            ->setSearchArea([
                ['text', 'send_mid', '发送人'],
            ])
            ->addColumns([
                ['id', 'ID'],
                ['group_id', '所在群'],
                ['send_mid', '发送人'],
                ['type','类型', [1 =>'文本',2 => '语音',3 => '图片',5=>'视频']],
                //['content', '内容'],
                ['content', '内容','link',url('content',['id'=>'__id__']), '_blank','pop', '内容'],
                ['create_time', '发送时间', 'datetime'],
                ['right_button', '操作', 'btn']
            ])
            //->setColumnWidth('last_login_ip', 180)
            //->addRightButton('edit')
            ->addRightButton('delete')
            ->setRowList($data_list)
            ->fetch();
    }

    public function content()
    {
        $params = $this->request->param();
        $id = $params['id'];
        $one = Db::table('df_message_group')->where('id',$id)->field('id,type,content')->find();
        if(!empty($one)){
            if($one['type']==3){
                $content = json_decode($one['content'],true);
                $src = $content['url'];
                return '<img src="'.$src.'">';
            }elseif($one['type']==1){
                echo $one['content'];
            }elseif($one['type']==2){
                echo '[语音]';
            }elseif($one['type']==5){
                echo '[视频]';
            }
        }
    }

    public function mask_keywords(){
        $id = KeywordsMaskModel::order('id','desc')->value('id');
        if ($id === null) $this->error('缺少参数');

        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            if (KeywordsMaskModel::update($data)) {
                $this->success('设置成功');
            } else {
                $this->error('设置失败');
            }
        }
        // 获取数据
        $info = KeywordsMaskModel::where('id', $id)->find();
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('参数设置') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['hidden', 'id'],
                ['tags', 'keywords', '聊天禁止发送关键字'],
            ])
            ->setFormData($info) // 设置表单数据
            ->fetch();
    }

    public function edit($id = null)
    {
        if ($id === null) $this->error('缺少参数');
        $basepath = 'http://'.$_SERVER['HTTP_HOST'];
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if(is_array($data['manage'])){
                $data['manage'] = implode(',',$data['manage']);
            }
            //如果更换图片
            if($data['icon']!=''){
                $file = new Attachment();
                $data['icon'] = $basepath.$file->getFilePath($data['icon']);
            }
            if (GroupModel::update($data)) {
                $this->success('编辑成功', 'index');
            } else {
                $this->error('编辑失败');
            }
        }
        // 获取数据
        $info = Db::table('df_group')->where('id', $id)->find();
        //查找附件中的值
        $attachPath = str_replace($basepath.'/','',$info['icon']);
        $attachmentId = Db::table('df_admin_attachment')->where('path',$attachPath)->value('id');
        $info['icon'] = $attachmentId;

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
                ['image', 'icon', '群图标'],
                ['text', 'name', '名称'],
                ['textarea', 'description', '群描述'],
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
                //删除相关的聊天记录
                Db::table('df_message_group')->where('group_id','in',$ids)->delete();
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