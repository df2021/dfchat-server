<?php


namespace app\member\admin;


use app\admin\controller\Admin;
use app\common\builder\ZBuilder;
use app\member\model\Message as MessageModel;
use think\Db;

class Message extends Admin
{
    public function index()
    {
        $map = $this->getMap();
        //dump($map);
        foreach ($map as $k=>$item){
            if($item[0]=='send_mid' || $item[0]=='to_mid'){
                $map[$k][2] = Db::table('df_member')->where('username|nickname',$item[2])->value('id');
            }
        }

        $data_list = MessageModel::where($map)
            ->order($this->getOrder('id desc'))
            ->paginate();

        return ZBuilder::make('table')
            ->setPageTitle('聊天记录') // 设置页面标题
            ->setTableName('message')
            //->setSearch(['name' => '名称']) // 设置搜索参数
            ->setSearchArea([
                ['text', 'send_mid', '发送人'],
                ['text', 'to_mid', '接收人'],
            ])
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