<?php


namespace app\http\chat;


use think\Db;

class Message extends Index
{

    public function connected()
    {
        $data = $this->data;
        $frame = $this->frame;
        $server = $this->server;
        $client = [
            'user_id'=>$data['user_id'],
            'type' => $data['type'],
            'fd' => $frame->fd
        ];
        //redis 先存入,如果没有存入成功再存mysql数据库 todo
        $id = Db::table('df_socket_client')->insert($client);
        if($id>0){
            $server->push($frame->fd,'与服务器连接成功');
        }
    }


}