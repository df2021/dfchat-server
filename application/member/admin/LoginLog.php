<?php


namespace app\member\admin;


use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\member\model\Member;
use think\Db;

class LoginLog extends Admin
{
    public function index()
    {
        $where = $this->getMap();

        $data_list = Db::table('df_member_login_log')->where($where)
            ->order($this->getOrder('login_time desc'))
            ->paginate();

        return ZBuilder::make('table')
            ->addOrder('id,last_login_time')
            ->setPageTitle('会员登录日志') // 设置页面标题
            ->setTableName('member_login_log')
            ->setSearch(['username' => '用户名','real_ip'=>'真实IP']) // 设置搜索参数
            ->hideCheckbox()
            ->addColumns([
                //['id', 'ID'],
                ['username', '用户名'],
                ['real_ip', '真实IP'],
                ['local_address', '所在地'],
                ['op_system', '终端设备'],
                ['device_info', '设备信息'],
                ['system_info', '系统信息'],
                ['browser_info', '浏览器信息'],
                ['is_robot', '是否机器人',[0=>'否',1=>'是']],
                ['login_time', '登录时间', 'datetime'],
                ['right_button', '操作', 'btn']
            ])
            //->setColumnWidth('last_login_ip', 180)
            //->addTopButtons('add,enable,disable,delete') // 批量添加顶部按钮
            //->addRightButton('edit')
            ->addRightButton('delete')
            //->addFilter('status', ['启用', '禁用'])
            ->setRowList($data_list)
            ->fetch();
    }
}