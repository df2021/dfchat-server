<?php


namespace app\member\admin;


use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\member\model\SystemConfig as SystemConfigModel;

class SystemConfig extends Admin
{
    public function index()
    {
        $id = SystemConfigModel::order('id','desc')->value('id');
        if ($id === null) $this->error('缺少参数');

        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            if (SystemConfigModel::update($data)) {
                $this->success('设置成功', 'index');
            } else {
                $this->error('设置失败');
            }
        }
        // 获取数据
        $info = SystemConfigModel::where('id', $id)->find();
        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('参数设置') // 设置页面标题
            ->addFormItems([ // 批量添加表单项
                ['hidden', 'id'],
                ['textarea', 'user_agreement', '用户协议'],
                ['textarea', 'privacy_policy', '隐私条款'],
//                ['textarea', 'user_agreement', '用户协议'],
//                ['textarea', 'privacy_policy', '隐私条款'],
                ['textarea', 'welcome', '问候语'],
                ['textarea', 'open_remark', '客服开场白'],
            ])
            ->setFormData($info) // 设置表单数据
            ->fetch();
    }
}