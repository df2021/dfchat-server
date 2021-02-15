<?php


namespace app\http;


use app\http\chat\Index;
use think\Db;
use think\db\Where;
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
                    $nowTime = time();
                    switch ($requestAction) {
                        //初始连接绑定客户端
                        case 'connected':
                            $client = [
                                'user_id'=>$send_mid,
                                'type' => $data['type'],
                                'fd' => $frame->fd
                            ];
                            //redis 先存入,如果没有存入成功再存mysql数据库 todo
                            //若服务器异常情况下没有清除绑定的客户端数据，先清空
                            if($frame->fd==1){
                                Db::table('df_socket_client')->delete(true);
                            }
                            $id = Db::table('df_socket_client')->insert($client);
                            if($id>0){
                                $res = [
                                    'type' => 'connect',
                                    'data' => '与服务器连接绑定成功'
                                ];
                                $res = json_encode($res,JSON_UNESCAPED_UNICODE);
                                $server->push($frame->fd,$res);
                            }
                            break;
                        //拉取验证、好友、群列表信息
                        case 'pullList':
                            $list = [];
                            //验证(未验证的)
                            $apply_list = Db::table('df_apply')
                                ->where('to_mid',$send_mid)
                                ->where('status',0)
                                ->order('id','desc')
                                ->select();
                            foreach ($apply_list as $apply)
                            {
                                $one['userId'] = $apply['send_mid'];
                                $one['name'] = '验证消息';//可根据type字段来自定义
                                $one['images'] = $apply['icon'];
                                $one['updateTime'] = uc_time_ago($apply['update_time']) ;
                                $one['listType'] = 3;
                                $one['firstChar'] = '';
                                $one['status'] = $apply['status'];
                                $one['msg'] = $apply['content'];
                                $one['type'] = $apply['type'];
                                $one['num'] = 1;
                                $one['dataId'] = $apply['id'];
                                array_push($list,$one);
                            }

                            //好友(未读的)
                            $map1= [
                                ['status','=',1],
                                ['to_mid','=',$send_mid]
                            ];
                            $field_friend = 'id,type,send_mid,to_mid,content,status,update_time,send_time,read_time,receive_time';
                            $subQuery_friend = Db::table('df_message')
                                ->field($field_friend)
                                ->where($map1)
                                ->order('update_time','desc')
                                ->buildSql();
                            $last_msg = Db::table($subQuery_friend.' f')
                                ->field($field_friend.', count(f.status) as num')
                                ->group('send_mid')
                                ->limit(500)
                                ->select();
                            if(!empty($last_msg)){
                                foreach ($last_msg as $item){
                                    $friend = Db::table('df_member')
                                        ->where('id',$item['send_mid'])
                                        ->where('status',1)
                                        ->field('id,username,nickname,avatar')
                                        ->find();
                                    $one['userId'] = $item['send_mid'];
                                    $one['name'] = !empty($friend['nickname']) ? $friend['nickname'] : $friend['username'];//可根据type字段来自定义
                                    $one['images'] = $friend['avatar'];
                                    $one['firstChar'] = getFirstChar($one['name']);
                                    $one['listType'] = 1;
                                    $one['updateTime'] = uc_time_ago($item['update_time']);
                                    $one['msg'] = $item['content'];
                                    $one['num'] = $item['num'];
                                    $one['status'] = 1;
                                    $one['type'] = $item['type'];
                                    $one['dataId'] = $item['id'];
                                    array_push($list,$one);
                                }
                            }


                            //群组(未读的)
                            $map1_group= [
                                ['status','=',1],
                                ['to_mid','=',$send_mid]
                            ];
                            $field_group = 'id,group_id,type,send_mid,content,status,update_time,send_time,read_time,receive_time';
                            $subQuery_group = Db::table('df_message_group')
                                ->where($map1_group)
                                ->field($field_group)
                                ->order('update_time','desc')
                                ->buildSql();
                            $last_group_msg = Db::table($subQuery_group.' g')
                                ->field($field_group.',count(g.status) as num')
                                ->group('group_id')
                                ->limit(50)
                                ->select();
                            if(!empty($last_group_msg)){
                                foreach ($last_group_msg as $item){
                                    //查群信息
                                    $group = Db::table('df_group')
                                        ->where('group_id',$item['group_id'])
                                        ->where('status',1)
                                        ->field('id,name,status,icon,created_mid')
                                        ->find();

                                    $one['userId'] = $item['created_mid'];
                                    $one['groupId'] = $group['id'];
                                    $one['name'] = $group['name'];
                                    $one['firstChar'] = '☆';
                                    $one['images'] = $group['icon'];
                                    $one['listType'] = 2;
                                    $one['num'] = $item['num'];
                                    $one['updateTime'] = uc_time_ago($item['update_time']);
                                    $one['msg'] = $item['content'];
                                    $one['status'] = $item['status'];
                                    $one['type'] = $item['type'];
                                    $one['dataId'] = $item['id'];
                                    array_push($list,$one);
                                }
                            }

                            $res = [
                                'type'=>'pullList',
                                'data'=>$list
                            ];
                            $res = json_encode($res,JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd,$res);
                            break;
                        //添加好友
                        case 'addFriend':
                            $to_mid = $data['to_mid'];
                            $content = $data['content'];

                            $apply_id = Db::table('df_apply')
                                ->where('send_mid',$send_mid)
                                ->where('to_mid',$to_mid)
                                ->value('id');
                            if($apply_id>0){
                                $server->push($frame->fd,'已发出过同样的申请');
                            }else{
                                $insert_data = [
                                    'send_mid' => $send_mid,
                                    'to_mid' => $to_mid,
                                    'type' => 1,
                                    'status' => 0,
                                    'content' => $content,
                                    'create_time' => $nowTime,
                                    'update_time' => $nowTime
                                ];

                                $insert_id = Db::table('df_apply')->insert($insert_data,false,true,'id');
                                if($insert_id>0){
                                    //如果对方在线，向对方发送验证通知 redis优化 todo
                                    $to_fds = Db::table('df_socket_client')->where('user_id',$to_mid)->column('fd');
                                    if(!empty($to_fds)){
                                        $insert_data['id'] = $insert_id;
                                        $res = [
                                            'type' => 'receiveApply',
                                            'data' => [
                                                'dataId'=>$insert_id,
                                                'userId'=>$send_mid,
                                                'name'=> '验证消息',
                                                'images'=>'/static/image/noteico.png',
                                                'updateTime'=> '刚刚',
                                                'listType'=>3,
                                                'type'=>1,
                                                'num'=>1,
                                                'status'=>0,
                                                'msg'=>$content,
                                            ]
                                        ];
                                        $res = json_encode($res,JSON_UNESCAPED_UNICODE);
                                        foreach ($to_fds as $fd){
                                            if($server->isEstablished($fd)){
                                                $server->push($fd,$res);
                                            }
                                        }
                                    }
                                }
                            }

                            break;
                        //添加群组
                        case 'createGroup':
                            Db::startTrans();
                            try {
                                $fds = Db::table('df_socket_client')->where('user_id',$send_mid)->column('fd');
                                //检查是否存在一样的
                                $isGid = Db::table('df_group')->where('name',$data['name'])->value('id');
                                if($isGid>0){
                                    $checked = [
                                        'type' => 'checkGroup',
                                        'data' => [
                                            'code' => -1,
                                            'error' => '群昵称已经存在'
                                        ]
                                    ];
                                }else{
                                    $checked = [
                                        'type' => 'checkGroup',
                                        'data' => [
                                            'code' => 0,
                                            'info' => 'success'
                                        ]
                                    ];
                                }
                                $checked = json_encode($checked,JSON_UNESCAPED_UNICODE);
                                $server->push($frame->fd,$checked);
                                //===============================================
                                $group_id = Db::table('df_group')->insert([
                                    'created_mid' => $send_mid,
                                    'name' => $data['name'],
                                    'status' => 1,
                                    'description' => $data['description'],
                                    'manage' => $send_mid,
                                    'members' => $send_mid,
                                    'create_time' => $nowTime,
                                    'update_time' => $nowTime,
                                ],false,true,'id');
                                //更新个人所在群信息
                                $my_groups = Db::table('df_member')->where('id',$send_mid)->value('groups');
                                if(!empty($my_groups)){
                                    $groups_new = $my_groups.','.$group_id;
                                    Db::table('df_member')->where('id',$send_mid)->setField('groups',$groups_new);
                                }else{
                                    Db::table('df_member')->where('id',$send_mid)->setField('groups',$group_id);
                                }

                                Db::commit();
                                $res = [
                                    'type' => 'addedGroup',
                                    'data' => [
                                        'dataId'=>$group_id,
                                        'groupId'=>$group_id,
                                        'userId'=>$send_mid,
                                        'name'=> $data['name'],
                                        'firstChar'=>'☆',
                                        'images'=>'/static/image/group.png',
                                        'updateTime'=> '刚刚',
                                        'listType'=>2,
                                        'type'=>1,
                                        'num'=>1,
                                        'status'=>0,
                                        'msg'=>'创建成功',
                                    ]
                                ];
                                $res = json_encode($res,JSON_UNESCAPED_UNICODE);
                                foreach ($fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res);
                                    }
                                }
                            }catch (\Exception $exception){
                                Db::rollback();
                            }
                            break;
                        //获取好友信息
                        case 'getFriend':
                            $friendId = $data['friend_id'];
                            $friend = Db::table('df_member')
                                ->where('id',$friendId)
                                ->where('status',1)
                                ->find();
                            if(!empty($friend)){
                                $is_friend = Db::table('df_friends')
                                    ->where('mid',$send_mid)
                                    ->where('friend_mid',$friendId)
                                    ->where('status',1)
                                    ->value('id');
                                $friend['is_friend'] = $is_friend>0 ? 1 : 0;
                                $friend = json_encode($friend,JSON_UNESCAPED_UNICODE);
                                $server->push($frame->fd,$friend);
                            }

                            break;
                        //获取群信息
                        case 'getGroupInfo':
                            $gid = $data['group_id'];
                            $groupInfo = Db::table('df_group')->where('id',$gid)->where('status',1)->find();
                            $groupInfo['createTime'] = uc_time_ago($groupInfo['create_time']);
                            $manageIds = explode(',',$groupInfo['manage']);
                            $memberIds = explode(',',$groupInfo['members']);
                            //
                            $manageList = Db::table('df_member')
                                ->where('id','in',$manageIds)
                                ->where('status',1)
                                ->field('id,username,nickname,avatar')->select();
                            $memberList = Db::table('df_member')
                                ->where('id','in',$memberIds)
                                ->where('status',1)
                                ->field('id,username,nickname,avatar')->select();
                            foreach ($manageList as $k=>$v){
                                $name = !empty($v['nickname']) ? $v['nickname'] : $v['username'];
                                $manageList[$k]['firstChar'] = getFirstChar($name);
                                $manageList[$k]['name'] = $name;
                                $manageList[$k]['userId'] = $v['id'];
                            }
                            foreach ($memberList as $k=>$v){
                                $name = !empty($v['nickname']) ? $v['nickname'] : $v['username'];
                                $memberList[$k]['firstChar'] = getFirstChar($name);
                                $memberList[$k]['name'] = $name;
                                $memberList[$k]['userId'] = $v['id'];
                            }
                            unset($groupInfo['manage']);
                            unset($groupInfo['members']);
                            $list = [
                                'type' => 'getGroupInfo',
                                'data' => [
                                    'groupInfo' => $groupInfo,
                                    'manageList' => $manageList,
                                    'memberList' => $memberList,
                                ]
                            ];
                            $res = json_encode($list,JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd,$res);
                            break;
                        //添加群成员
                        case 'addGroupMember':
                            $gid = $data['group_id'];
                            $userIds = json_decode($data['user_ids'],true);
                            $newIds = [];
                            $one = Db::table('df_group')
                                ->where('id',$gid)
                                ->where('status',1)
                                ->find();
                            $members = $one['members'];
                            if(!empty($members)){
                                $members_arr = explode(',',$members);
                                $addUsers = $members_arr;
                                foreach ($userIds as $id){
                                    if(!in_array($id,$members_arr)){ //没有加入到群里的情况
                                        $addUsers[] = $id;
                                        $newIds[] = $id;
                                    }
                                }
                            }else{
                                $addUsers = $userIds;
                                $newIds = $userIds;
                            }
                            //统一序列化,重置成员
                            $addUsers = implode(',',$addUsers);
                            $members = Db::table('df_group')->where('id',$gid)->setField('members',$addUsers);
                            if($members){
                                $fds = Db::table('df_socket_client')->where('user_id','in',$newIds)->column('fd');
                                $me_res = json_encode([
                                    'type' => 'addGroupMember',
                                    'data' => [
                                        'code' => 0,
                                        'info' => '添加成功'
                                    ]
                                ],JSON_UNESCAPED_UNICODE);
                                $server->push($frame->fd,$me_res);
                                //
                                $res = json_encode([
                                    'type' => 'addedGroup',
                                    'data' => [
                                        'dataId'=>$gid,
                                        'groupId'=>$gid,
                                        'userId'=>$one['created_mid'],
                                        'name'=> $one['name'],
                                        'firstChar'=>'☆',
                                        'images'=>$one['icon'],
                                        'updateTime'=> '刚刚',
                                        'listType'=>2,
                                        'type'=>1,
                                        'num'=>1,
                                        'status'=>0,
                                        'msg'=>'你加入了该群',
                                    ]
                                ],JSON_UNESCAPED_UNICODE);
                                foreach ($fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res);
                                    }
                                }
                                //群里所有人发送加入群的消息
                            }
                            break;
                        //删除群成员
                        case 'delGroupMember':
                            $gid = $data['group_id'];
                            $userIds = json_decode($data['user_ids'],true);
                            $one = Db::table('df_group')
                                ->where('id',$gid)
                                ->where('status',1)
                                ->find();
                            $members = $one['members'];
                            $manage = $one['manage'];
                            $manage_arr = explode(',',$manage);
                            $members_arr = explode(',',$members);
                            if(!in_array($send_mid,$manage_arr)){
                                $data = [
                                    'code' => -1,
                                    'error' => '权限不足'
                                ];
                            }else{
                                if(in_array($send_mid,$userIds)){
                                    $data = [
                                        'code' => -1,
                                        'error' => '不能删除自己'
                                    ];
                                }else{
                                    $newMember = array_diff($members_arr, $userIds);
                                    $newMember = implode(',',$newMember);
                                    $r = Db::table('df_group')->where('id',$gid)->setField('members',$newMember);
                                    if($r){
                                        $data = [
                                            'code' => 0,
                                            'info' => '成功移出群'
                                        ];
                                    }
                                }

                            }
                            $me_res = json_encode([
                                'type' => 'delGroupMember',
                                'data' => $data
                            ],JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd,$me_res);

                            break;
                        //处理申请
                        case 'handleApply':
                            Db::startTrans();
                            try {
                                Db::table('df_apply')
                                    ->where('id',$data['dataId'])
                                    ->update(['status'=>1,'update_time'=>$nowTime]);
                                $I1 = Db::table('df_friends')->insert([
                                    'mid'=>$data['user_id'],
                                    'friend_mid'=>$send_mid,
                                    'status'=>1,
                                    'create_time' => $nowTime,
                                    'update_time' => $nowTime,
                                ],false,true,'id');
                                $I2 = Db::table('df_friends')->insert([
                                    'mid'=>$send_mid,
                                    'friend_mid'=>$data['user_id'],
                                    'status'=>1,
                                    'create_time' => $nowTime,
                                    'update_time' => $nowTime,
                                ],false,true,'id');
                                $f1 = Db::table('df_member')->where('id',$send_mid)->find();
                                $f2 = Db::table('df_member')->where('id',$data['user_id'])->find();
                                $f1_fds = Db::table('df_socket_client')->where('user_id',$send_mid)->column('fd');
                                $f2_fds = Db::table('df_socket_client')->where('user_id',$data['user_id'])->column('fd');
                                Db::commit();
                                //向对方发送验证通过信息
                                $f1_nickname = !empty($f1['nickname']) ? $f1['nickname'] : $f1['username'];
                                $f2_nickname = !empty($f2['nickname']) ? $f2['nickname'] : $f2['username'];
                                $res1 = json_encode([
                                    'type' => 'addedFriend',
                                    'data' => [
                                        'dataId'=>$I2,
                                        'userId'=>$data['user_id'],
                                        'name'=>$f2_nickname,
                                        'firstChar'=> getFirstChar($f2_nickname),
                                        'signature'=> $f2['signature'],
                                        'images'=>$f2['avatar'],
                                        'updateTime'=> '刚刚',
                                        'listType'=>1,
                                        'type'=>1,
                                        'num'=>1,
                                        'status'=>0,
                                        'msg'=>$f2_nickname.'成为您好友',
                                    ]
                                ],JSON_UNESCAPED_UNICODE);
                                $res2 = json_encode([
                                    'type' => 'applyAddedFriend',
                                    'data' => [
                                        'dataId'=>$I1,
                                        'userId'=>$send_mid,
                                        'name'=>$f1_nickname,
                                        'firstChar'=>getFirstChar($f1_nickname),
                                        'signature'=> $f1['signature'],
                                        'images'=>$f1['avatar'],
                                        'updateTime'=> '刚刚',
                                        'listType'=>1,
                                        'type'=>1,
                                        'num'=>1,
                                        'status'=>0,
                                        'msg'=>$f1_nickname.'成为您的好友',
                                    ]
                                ],JSON_UNESCAPED_UNICODE);

                                if(!empty($f1_fds)){
                                    foreach ($f1_fds as $fd){
                                        if($server->isEstablished($fd)){
                                            $server->push($fd,$res1);
                                        }
                                    }
                                }

                                if(!empty($f2_fds)){
                                    foreach ($f2_fds as $fd){
                                        if($server->isEstablished($fd)){
                                            $server->push($fd,$res2);
                                        }
                                    }
                                }

                            }catch (\Exception $exception){
                                Db::rollback();
                            }

                            break;
                        //搜索好友
                        case 'search':
                            $words = trim($data['search_word']);

                            if(!empty($words)){
                                $list = Db::table('df_member')
                                    //->where('username','like','%'.$words.'%')
                                    ->where('username','like',$words.'%')
                                    ->field('id,username,nickname,avatar,signature')
                                    ->limit(10)
                                    ->select();
                                $list = json_encode($list,JSON_UNESCAPED_UNICODE);
                                $server->push($frame->fd, $list);
                            }
                            break;
                        //聊天窗口获取消息
                        case 'getMessage':
                            $to_mid = $data['user_id'];
                            $from_mid = $data['to_user_id'];
                            $lastId = $data['lastId'];//起始ID
                            $map1 = [];
                            $map2 = [];
                            if($lastId>0){
                                $map1[] = ['id','<',$lastId];
                                $map2[] = ['id','<',$lastId];
                            }
                            $map1[] = ['send_mid','=',$to_mid];
                            $map1[] = ['to_mid','=',$from_mid];
                            $map2[] = ['send_mid','=',$from_mid];
                            $map2[] = ['to_mid','=',$to_mid];

                            $limit = (isset($data['limit']) && $data['limit']<=15) ? $data['limit'] : 15 ;
                            $msg_list = Db::table('df_message')
                                ->whereOr([$map1,$map2])
                                ->limit($limit)
                                ->order('create_time','desc')
                                ->select();
                            if(!empty($msg_list)){
                                foreach ($msg_list as $k=>$v)
                                {
                                    $msg_list[$k]['create_time'] = date('i:s',$v['create_time']);
                                }
                                //优化-如果涉及图片等可尝试使用base_64进行编码
                                $res = [
                                    'type' => 'getMessage',
                                    'data' => $msg_list
                                ];
                                $res = json_encode($res,JSON_UNESCAPED_UNICODE);
                                $me_fds = Db::table('df_socket_client')->where('user_id',$send_mid)->column('fd');
                                // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                                foreach ($me_fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res);
                                    }
                                }
                            }
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

                            $me_fds = Db::table('df_socket_client')->where('user_id',$send_mid)->column('fd');
                            // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                            foreach ($me_fds as $fd){
                                if($server->isEstablished($fd)){
                                    $server->push($fd,$msg_list);
                                }
                            }
                            break;
                        // 聊天窗口发送消息
                        case 'sendMsg':
                            $content = $data['content'];
                            $send_mid = $data['user_id'];
                            $to_mid = $data['to_user_id'];
                            //保存消息到数据库
                            $message = [
                                'send_mid' => $send_mid,
                                'to_mid' => $to_mid,
                                'type' => $data['type'],
                                'status' => 1,
                                'content' => $content,
                                'create_time' => $nowTime,
                                'update_time' => $nowTime,
                                'send_time' => $nowTime,
                            ];
                            $message_id = Db::table('df_message')->insert($message,false,true,'id');
                            if($message_id>0){
                                $message['id'] = $message_id;
                                $message['update_time'] = '刚刚';
                                //消息保存成功下发给双方
                                $res_me = json_encode([
                                    'type'=>'receiveMassage',
                                    'data'=>$message
                                ],JSON_UNESCAPED_UNICODE);

                                $me_fds = Db::table('df_socket_client')->where('user_id',$send_mid)->column('fd');
                                foreach ($me_fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res_me);
                                    }
                                }

                                $res_to = json_encode([
                                    'type'=>'receiveMassage',
                                    'data'=>$message
                                ],JSON_UNESCAPED_UNICODE);
                                $sendUserInfo = Db::table('df_member')
                                    ->where('id',$message['send_mid'])
                                    ->where('status',1)
                                    ->field('id,username,nickname,avatar')
                                    ->find();
                                $one['userId'] = $sendUserInfo['id'];
                                $one['name'] = !empty($sendUserInfo['nickname']) ? $sendUserInfo['nickname'] : $sendUserInfo['username'];;
                                $one['firstChar'] = '';
                                $one['images'] = $sendUserInfo['avatar'];
                                $one['listType'] = 1;
                                $one['num'] = 1;
                                $one['updateTime'] = '刚刚';
                                $one['msg'] = $message['content'];
                                $one['status'] = 1;
                                $one['type'] = $message['type'];
                                $one['dataId'] = $message_id;
                                $push_to = json_encode([
                                    'type'=>'pushMessage',
                                    'data'=>$one
                                ],JSON_UNESCAPED_UNICODE);
                                //redis 读取优化
                                $fds = Db::table('df_socket_client')->where('user_id',$to_mid)->column('fd');
                                foreach ($fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res_to);
                                        $server->push($fd,$push_to);
                                    }
                                }
                            }
                            break;
                        case 'getAddressBook':
                            $list = [];
                            //好友
                            $friend_ids = Db::table('df_friends')
                                ->where('mid',$send_mid)
                                ->where('status',1)
                                ->column('friend_mid');
                            if(!empty($friend_ids)) {
                                $friends = Db::table('df_member')
                                    ->where('id', 'in', $friend_ids)
                                    ->where('status', 1)
                                    ->field('id,username,nickname,avatar')
                                    ->limit(500)
                                    ->select();
                                foreach ($friends as $friend){
                                    $one['userId'] = $friend['id'];
                                    $one['name'] = !empty($friend['nickname']) ? $friend['nickname'] : $friend['username'];
                                    $one['firstChar'] = getFirstChar($one['name']);
                                    $one['images'] = $friend['avatar'];
                                    $one['listType'] = 1;
                                    $one['num'] = 0;
                                    $one['updateTime'] = '刚刚';
                                    $one['msg'] = '';
                                    $one['status'] = 1;
                                    $one['type'] = 1;
                                    $one['dataId'] = $one['userId'];
                                    array_push($list,$one);
                                }

                            }
                            //群
                            $group_ids = Db::table('df_member')->where('id',$send_mid)->value('groups');
                            if(!empty($group_ids)){
                                $group_ids = explode(',',$group_ids);
                                $groups = Db::table('df_group')
                                    ->where('id','in',$group_ids)
                                    ->where('status',1)
                                    ->field('id,name,status,icon,created_mid')
                                    ->limit(50)
                                    ->select();
                                if(!empty($groups)){
                                    //查找群里最近一条消息
                                    foreach ($groups as $group){
                                        $one['userId'] = $group['created_mid'];
                                        $one['groupId'] = $group['id'];
                                        $one['name'] = $group['name'];
                                        $one['firstChar'] = '☆';
                                        $one['images'] = $group['icon'];
                                        $one['listType'] = 2;
                                        $one['num'] = 0;
                                        $one['updateTime'] = '刚刚';
                                        $one['msg'] = '';
                                        $one['status'] = 1;
                                        $one['type'] = 1;
                                        $one['dataId'] = $one['userId'];
                                        array_push($list,$one);
                                    }

                                }
                            }
                            $res = [
                                'type'=>'getAddressBook',
                                'data'=>$list
                            ];
                            $res = json_encode($res,JSON_UNESCAPED_UNICODE);
                            $fds = Db::table('df_socket_client')->where('user_id',$send_mid)->column('fd');
                            foreach ($fds as $fd){
                                if($server->isEstablished($fd)){
                                    $server->push($fd,$res);
                                }
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