<?php


namespace app\http;


use app\http\chat\Index;
use think\Db;
use think\swoole\Server;

class Swoole extends Server
{
    protected $host = '0.0.0.0';
    protected $serverType = 'socket';
    protected $port = 9502;
    protected $option = [
        'worker_num'=> 1, //调试时改为1
        'daemonize'	=> false, //调试时设为false
        'backlog'	=> 128
    ];

    /*public function onReceive($server, $fd, $from_id, $data)
    {
        $server->send($fd, 'Swoole: '.$data);
    }*/

    public function onOpen($server, $request)
    {
        echo "server: handshake success with fd{$request->fd}\n";

    }

    /*public function onMessage($server, $frame)
    {
        //后期优化做成swoole框架
        $chat = new Index($server,$frame);
        $send_mid = $chat->checkToken();
        if($send_mid>0){
            $chat->requestAction();
        }
    }*/
    public function onMessage($server, $frame)
    {
        if(!empty($frame->data)){
            $data = json_decode($frame->data,true);
            if(isset($data['access_token']) && isset($data['action'])){
                $token_json = decryptToken($data['access_token']);
                $token_info = json_decode($token_json,true);

                //验证token
                $send_mid = Db::table('df_member')->where('id',$token_info['mid'])->value('id');
                if($send_mid>0){
                    //
                    $requestAction = $data['action'];
                    switch ($requestAction) {
                        case 'connected':
                            $client = [
                                'user_id'=>$send_mid,
                                'type' => $data['type'],
                                'fd' => $frame->fd
                            ];
                            //redis 先存入,如果没有存入成功再存mysql数据库 todo
                            $id = Db::table('df_socket_client')->insert($client);
                            if($id>0){
                                $server->push($frame->fd,'与服务器连接绑定成功');
                            }
                            break;
                        case 'addFriend':

                            break;
                        case 'search':
                            $words = $data['search_word'];
                            $list = '';
                            if(!empty($words)){
                                $list = Db::table('df_member')
                                    ->where('username','like','%'.$words.'%')
                                    ->field('id,username,nickname,avatar,signature')
                                    ->limit(10)
                                    ->select();
                                $list = json_encode($list,JSON_UNESCAPED_UNICODE);
                            }
                            echo $list;
                            $server->push($frame->fd, $list);
                            break;
                        case 'receiveMsg':
                            $to_mid = $data['user_id'];
                            $send_mid = $data['to_user_id'];
                            $map1= [
                                ['send_mid','=',$to_mid],
                                ['to_mid','=',$send_mid]
                            ];
                            $map2= [
                                ['send_mid','=',$send_mid],
                                ['to_mid','=',$to_mid]
                            ];
                            $offset = $data['offset'];//分页指针
                            $limit = $data['limit']<=15 ? $data['limit'] : 15 ;
                            $msg_list = Db::table('df_message')
                                ->whereOr([$map1,$map2])
                                ->limit($offset,$limit)
                                ->order('create_time','desc')
                                ->select();
                            foreach ($msg_list as $k=>$v)
                            {
                                if($v['create_time']>0){
                                    $msg_list[$k]['create_time'] = date('i:s',$v['create_time']);
                                }
                            }
                            //优化-如果涉及图片等可尝试使用base_64进行编码
                            $msg_list = json_encode($msg_list,JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd,$msg_list);
                            break;
                        case 'chatList':
                            $mid = $data['user_id'];
                            $msg_list = ['friend'=>[],'group'=>[]];
                            $friend = Db::table('df_friends')
                                ->where('mid',$mid)
                                ->limit(10)
                                ->select();

                            if(!empty($data['groups'])){
                                $group_ids = explode(',',$data['groups']);
                                $group = Db::table('df_group')
                                    ->where('id','in',$group_ids)
                                    ->limit(10)
                                    ->select();
                                $msg_list['group'] = $group;
                            }

                            $msg_list['friend'] = $friend;

                            //优化-如果涉及图片等可尝试使用base_64进行编码
                            $msg_list = json_encode($msg_list,JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd,$msg_list);
                            break;
                        case 'sendMsg':

                            $content = $data['content'];
                            $send_mid = $data['user_id'];
                            $to_mid = $data['to_user_id'];
                            //redis 读取优化
                            //这里有可能一个用户登录了多个设备
                            $fds = Db::table('df_socket_client')->where('user_id',$send_mid)->column('fd');
                            //直接先发送消息
                            // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                            foreach ($fds as $fd){
                                if($server->isEstablished($fd)){
                                    $server->push($fd,$content);
                                }
                            }

                            //保存消息到数据库
                            $time = time();
                            $message = [
                                'send_mid' => $send_mid,
                                'to_mid' => $to_mid,
                                'type' => $data['type'],
                                'status' => 1,
                                'content' => $content,
                                'create_time' => $time,
                                'update_time' => $time,
                                'send_time' => $time,
                            ];
                            $message_id = Db::table('df_message')->insert($message);
                            if($message_id>0){
                                //返回状态
                                $server->push($frame->fd,'send_success');
                            }
                            break;

                    }
                }
            }
        }
    }

    public function onRequest($request, $response)
    {
        $response->end("<h1>Hello Swoole. #" . rand(1000, 9999) . "</h1>");
    }

    public function onClose($ser, $fd)
    {
        echo "client {$fd} closed\n";
        Db::table('df_socket_client')
        ->where('fd',$fd)
        ->delete();
    }
}