<?php


namespace app\member\admin;


use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\member\model\Message as MessageModel;

class Message extends Admin
{
    public function index()
    {
        $data_list = MessageModel::where($this->getMap())
            ->order($this->getOrder('id desc'))
            ->paginate();

        return ZBuilder::make('table')
            ->setPageTitle('聊天记录') // 设置页面标题
            ->setTableName('message')
            //->setSearch(['name' => '名称']) // 设置搜索参数
            ->addColumns([
                ['id', 'ID'],
                ['send_mid', '发送人'],
                ['to_mid', '接收人'],
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
}