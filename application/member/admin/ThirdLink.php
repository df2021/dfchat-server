<?php
namespace app\member\admin;

use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\member\model\ThirdLink as ThirdLinkModel;

class ThirdLink extends Admin
{
    public function index()
    {
        $data_list = ThirdLinkModel::where($this->getMap())
            ->order($this->getOrder('id desc'))
            ->paginate();

        return ZBuilder::make('table')
            ->setPageTitle('第三方链接管理') // 设置页面标题
            ->setTableName('third_link')
            ->setSearch(['name' => '名称']) // 设置搜索参数
            ->addColumns([
                ['id', 'ID'],
                ['name', '名称'],
                ['url', '链接地址','text.edit'],
                ['create_time', '创建时间', 'datetime'],
                ['update_time', '创建时间', 'datetime'],
                ['right_button', '操作', 'btn']
            ])
            //->setColumnWidth('last_login_ip', 180)
            ->addTopButtons('add') // 批量添加顶部按钮
            ->addRightButton('edit')
            ->addRightButton('delete')
            ->setRowList($data_list)
            ->fetch();
    }

    public function add()
    {
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if ($user = ThirdLinkModel::create($data)) {
                // 记录行为
                $this->success('新增成功', url('index'));
            } else {
                $this->error('新增失败');
            }
        }

        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('新增') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['text', 'name', '名称'],
                ['text', 'url', '链接地址'],
            ])
            ->fetch();
    }

    public function edit($id = null)
    {
        if ($id === null) $this->error('缺少参数');

        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            if (ThirdLinkModel::update($data)) {
                $this->success('编辑成功', 'index');
            } else {
                $this->error('编辑失败');
            }
        }
        // 获取数据
        $info = ThirdLinkModel::where('id', $id)->field('password', true)->find();
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('编辑') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['text', 'name', '名称'],
                ['text', 'url', '链接地址'],
            ])
            ->setFormData($info) // 设置表单数据
            ->fetch();
    }
}