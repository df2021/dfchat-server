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
        'worker_num'=> 4, //调试时改为1
        'daemonize'	=> true, //调试时设为false
        'backlog'	=> 1280
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
                                //->where('status',0)
                                ->order('id','desc')
                                ->select();
                            foreach ($apply_list as $apply)
                            {
                                $one['userId'] = $apply['send_mid'];
                                $one['name'] = '验证消息';//可根据type字段来自定义
                                $one['images'] = $apply['icon'];
                                $one['updateTime'] = uc_time_ago($apply['create_time']) ;
                                $one['listType'] = 3;
                                $one['firstChar'] = '';
                                $one['status'] = $apply['status'];
                                $one['msg'] = $apply['content'];
                                $one['type'] = $apply['type'];
                                $one['num'] = $apply['status']>0 ? 0 : 1;
                                $one['dataId'] = $apply['id'];
                                array_push($list,$one);
                            }

                            //好友
                            $map1= [
                                //['status','<',3],
                                ['to_mid|send_mid','=',$send_mid]
                            ];
                            $field_friend = 'id,type,send_mid,to_mid,content,status,create_time,update_time,send_time,read_time,receive_time';
                            $subQuery_friend = Db::table('df_message')
                                ->field($field_friend)
                                ->where($map1)
                                ->order('id','desc')
                                ->buildSql();
                            $last_msg = Db::table($subQuery_friend.' f')
                                ->field($field_friend.', sum(IF(f.status<3,1,0)) as num')
                                ->group('send_mid,to_mid')
                                //->limit(500)
                                ->select();

                            if(!empty($last_msg)){
                                $f_id = [];
                                foreach ($last_msg as $item){
                                    $sendId = $item['send_mid']==$send_mid ? $item['to_mid'] : $item['send_mid'];
                                    if($item['send_mid']==$send_mid){
                                        $item['num'] = 0;
                                    }
                                    if(!isset($f_id[$sendId])){
                                        $f_id[$sendId] = $item;
                                    }else{
                                        if($f_id[$sendId]['id']<$item['id']){
                                            $f_id[$sendId] = $item;
                                        }
                                    }
                                }
                                foreach ($f_id as $sid => $item){
                                    $friend = Db::table('df_member')
                                        ->where('id',$sid)
                                        ->where('status',1)
                                        ->field('id,username,nickname,avatar')
                                        ->find();
                                    $item['num'] += 0;
                                    $one['userId'] = $sid;
                                    $one['name'] = !empty($friend['nickname']) ? $friend['nickname'] : $friend['username'];//可根据type字段来自定义
                                    $one['images'] = $friend['avatar'];
                                    $one['firstChar'] = getFirstChar($one['name']);
                                    $one['listType'] = 1;
                                    $one['updateTime'] = uc_time_ago($item['create_time']);
                                    $one['msg'] = $item['content'];
                                    $one['num'] = $item['num'];
                                    $one['status'] = $item['status'];
                                    $one['type'] = $item['type'];
                                    $one['dataId'] = $item['id'];
                                    array_push($list,$one);
                                }

                            }

                            //群组
                            $me_in_group = Db::table('df_member')->where('id',$send_mid)->value('groups');
                            $groupIds = explode(',',$me_in_group);
                            /*$map1_group= [
                                ['group_id','in',$groupIds]
                            ];*/
                            $field_group = 'id,group_id,type,send_mid,content,status,create_time,update_time,send_time,read_time,receive_time';
                            $subQuery_group = Db::table('df_message_group')
                                ->where('group_id','in',$groupIds)
                                ->field($field_group)
                                ->order('id','desc')
                                ->buildSql();
                            $last_group_msg = Db::table($subQuery_group.' g')
                                ->field($field_group.', sum(IF(g.status>0,1,0)) as num')
                                ->group('group_id')
                                ->limit(50)
                                ->select();
                            if(!empty($last_group_msg)){
                                foreach ($last_group_msg as $item){
                                    //计算未读条数
                                    $read_num = Db::table('df_read_group_msg')
                                        ->where('group_id',$item['group_id'])
                                        ->where('uid',$send_mid)
                                        ->where('status',3)
                                        ->count();
                                    $unReadNum = $item['num']-$read_num;
                                    $unReadNum += 0;
                                    if($unReadNum<0){ //如果记录被删的情况
                                        $unReadNum=0;
                                    }
                                    //查群信息
                                    $group = Db::table('df_group')
                                        ->where('id',$item['group_id'])
                                        ->where('status',1)
                                        ->field('id,name,status,is_recommend,icon,created_mid')
                                        ->find();

                                    $one['userId'] = $group['created_mid'];
                                    $one['groupId'] = $group['id'];
                                    $one['name'] = $group['name'];
                                    $one['firstChar'] = '☆';
                                    $one['images'] = $group['icon'];
                                    $one['listType'] = 2;
                                    $one['num'] = $unReadNum;
                                    $one['updateTime'] = uc_time_ago($item['create_time']);
                                    $one['msg'] = $item['content'];
                                    $one['status'] = $item['status'];
                                    $one['is_recommend'] = 0;
                                    $one['type'] = $item['type'];
                                    $one['dataId'] = $item['id'];
                                    array_push($list,$one);
                                }
                            }

                            //添加推荐群
                            $recommendGroups = Db::table('df_group')
                                ->where('is_recommend',1)
                                ->field('id,name,status,is_recommend,description,icon,created_mid,create_time')
                                ->select();
                            foreach ($recommendGroups as $group){
                                $one['userId'] = $group['created_mid'];
                                $one['groupId'] = $group['id'];
                                $one['name'] = $group['name'];
                                $one['firstChar'] = '☆';
                                $one['images'] = $group['icon'];
                                $one['listType'] = 2;
                                $one['num'] = 0;
                                $one['updateTime'] = uc_time_ago($group['create_time']);
                                $one['msg'] = $group['description'];
                                $one['status'] = $group['status'];
                                $one['is_recommend'] = 1;
                                $one['type'] = 1;
                                $one['dataId'] = 'R'.$group['id'];
                                array_unshift($list,$one);
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
                            //拦截群成员加好友的情况
                            $groups = Db::table('df_group')->where('status',1)->field('id,name,manage,members')->select();
                            $groupManage = [];
                            $groupMember = [];
                            foreach ($groups as $groupItem){
                                $itemManage = explode(',',$groupItem['manage']);
                                $itemMember = explode(',',$groupItem['members']);
                                $groupManage = array_merge($groupManage,$itemManage);
                                $groupMember = array_merge($groupMember,$itemMember);
                            }
                            //对方是群内成员且不是管理员不能申请加好友
                            if(in_array($to_mid,$groupMember) && !in_array($to_mid,$groupManage)){
                                $checked = [
                                    'type' => 'checkAddFriend',
                                    'data' => [
                                        'code' => -1,
                                        'error' => '对方屏蔽加好友'
                                    ]
                                ];
                                $checked = json_encode($checked,JSON_UNESCAPED_UNICODE);
                                $server->push($frame->fd,$checked);
                                return null;
                            }
                            //发出申请
                            $apply_id = Db::table('df_apply')
                                ->where('send_mid',$send_mid)
                                ->where('to_mid',$to_mid)
                                ->where('status',0)
                                ->value('id');
                            if($apply_id>0){
                                $checked = [
                                    'type' => 'checkAddFriend',
                                    'data' => [
                                        'code' => -1,
                                        'error' => '已发出过同样的申请'
                                    ]
                                ];
                                $checked = json_encode($checked,JSON_UNESCAPED_UNICODE);
                                $server->push($frame->fd,$checked);
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
                                                'updateTime'=> uc_time_format($nowTime),
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
                        //申请入群
                        case 'applyAddGroup':
                            $to_mid = $data['to_mid'];
                            $groupId = $data['group_id'];
                            $groupInfo = Db::table('df_group')
                                ->field('id')
                                ->where('id',$groupId)
                                ->where('created_mid',$to_mid)
                                ->find();
                            if(!empty($groupInfo)){
                                //发出申请
                                $apply_id = Db::table('df_apply')
                                    ->where('send_mid',$send_mid)
                                    ->where('to_mid',$to_mid)
                                    ->where('status',0)
                                    ->value('id');
                                if($apply_id>0){
                                    $checked = [
                                        'type' => 'applyAddGroup',
                                        'data' => [
                                            'code' => -1,
                                            'error' => '已发出过同样的申请'
                                        ]
                                    ];
                                    $checked = json_encode($checked,JSON_UNESCAPED_UNICODE);
                                    $server->push($frame->fd,$checked);
                                }else{
                                    $applyUser = Db::table('df_member')->where('id',$send_mid)->value('username');
                                    $content = '用户名为:'.$applyUser.' 的用户申请入群';
                                    $insert_data = [
                                        'send_mid' => $send_mid,
                                        'to_mid' => $to_mid,
                                        'type' => 2,
                                        'status' => 0,
                                        'content' => $content,
                                        'create_time' => $nowTime,
                                        'update_time' => $nowTime
                                    ];

                                    $insert_id = Db::table('df_apply')->insert($insert_data,false,true,'id');
                                    if($insert_id>0){
                                        $checked = [
                                            'type' => 'applyAddGroup',
                                            'data' => [
                                                'code' => 0,
                                                'info' => '申请已发出'
                                            ]
                                        ];
                                        $checked = json_encode($checked,JSON_UNESCAPED_UNICODE);
                                        $server->push($frame->fd,$checked);
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
                                                    'updateTime'=> uc_time_format($nowTime),
                                                    'listType'=>3,
                                                    'type'=>2,
                                                    'groupId'=>$groupId,
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
                                $systemMsg = [
                                    'type' => 1,
                                    'status' => 1,
                                    'content' => '创建成功',
                                    'create_time' => $nowTime,
                                    'update_time' => $nowTime,
                                    'send_time' => $nowTime,
                                    'group_id' => $group_id,
                                ];
                                //保存消息到数据库
                                Db::table('df_message_group')->insert($systemMsg);
                                Db::commit();
                                $res = [
                                    'type' => 'addedGroup',
                                    'data' => [
                                        'dataId'=>$group_id,
                                        'groupId'=>$group_id,
                                        'userId'=>$send_mid,
                                        'name'=> $data['name'],
                                        'firstChar'=>'☆',
                                        'images'=>$_SERVER['HTTP_REFERER'].'static/home/img/group.png',
                                        'updateTime'=> uc_time_format($nowTime),
                                        'listType'=>2,
                                        'is_recommend'=>0,
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
                                //
                                $friend['dataId'] = $friend['id'];
                                $friend['userId'] = $friend['id'];
                                $friend['name'] = !empty($friend['nickname']) ? $friend['nickname'] : $friend['username'];
                                $friend['firstChar'] = getFirstChar($friend['name']);
//                                $friend['signature'] = $friend['signature'];
                                $friend['images'] = $friend['avatar'];
                                $friend['updateTime'] = $friend['update_time'];
                                $friend['listType'] = 1;
                                $res = json_encode([
                                    'type'=> 'getFriend',
                                    'data'=> $friend
                                ],JSON_UNESCAPED_UNICODE);

                                $server->push($frame->fd,$res);
                            }

                            break;
                        //删除好友
                        case 'delFriend':
                            if(!isset($data['friend_id'])){
                                return null;
                            }
                            $fid = $data['friend_id'];
                            if($fid==$send_mid){
                                return null;
                            }
                            Db::table('df_friends')
                                ->where('mid',$send_mid)
                                ->where('friend_mid',$fid)
                                ->delete(true);
                            Db::table('df_friends')
                                ->where('mid',$fid)
                                ->where('friend_mid',$send_mid)
                                ->delete(true);
                            Db::table('df_message')
                                ->where('send_mid',$fid)
                                ->where('to_mid',$send_mid)
                                ->delete(true);
                            Db::table('df_message')
                                ->where('send_mid',$send_mid)
                                ->where('to_mid',$fid)
                                ->delete(true);
                            $res = json_encode([
                                'type'=> 'delFriend',
                                'data'=> [
                                    'code' => 0,
                                    'info' => 'success'
                                ]
                            ],JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd, $res);
                            break;
                        case 'delMessage':
                            if(!isset($data['msg_id'])){
                                return null;
                            }
                            Db::table('df_message')->where('id',$data['msg_id'])->delete();
                            break;
                        //获取群信息
                        case 'getGroupInfo':
                            $gid = $data['group_id'];
                            $groupInfo = Db::table('df_group')->where('id',$gid)->where('status',1)->find();
                            $groupInfo['createTime'] = uc_time_ago($groupInfo['create_time']);
                            $manageIds = explode(',',$groupInfo['manage']);
                            $memberIds = explode(',',$groupInfo['members']);
                            $isAdded = 1;
                            $isManage = 1;
                            if(!in_array($send_mid,$memberIds)){
                                $isAdded = 0;
                                //return null;
                            }
                            if(!in_array($send_mid,$manageIds)){
                                $isManage = 0;
                            }
                            //
                            $manageList = Db::table('df_member')
                                ->where('id','in',$manageIds)
                                ->where('status',1)
                                ->field('id,username,nickname,avatar')->select();
                            $memberList = Db::table('df_member')
                                ->where('id','in',$memberIds)
                                ->where('status',1)
                                ->field('id,username,nickname,avatar')->select();
                            $first_manage = [];
                            foreach ($manageList as $k=>$v){
                                $name = !empty($v['nickname']) ? $v['nickname'] : $v['username'];
                                $manageList[$k]['firstChar'] = getFirstChar($name);
                                $manageList[$k]['name'] = $name;
                                $manageList[$k]['userId'] = $v['id'];
                                if($v['id']==$groupInfo['created_mid']){
                                    $first_manage = $manageList[$k];
                                    unset($manageList[$k]);
                                }
                            }
                            array_unshift($manageList,$first_manage);

                            $first_member = [];
                            foreach ($memberList as $k=>$v){
                                $name = !empty($v['nickname']) ? $v['nickname'] : $v['username'];
                                $memberList[$k]['firstChar'] = getFirstChar($name);
                                $memberList[$k]['name'] = $name;
                                $memberList[$k]['userId'] = $v['id'];
                                if($v['id']==$groupInfo['created_mid']){
                                    $first_member = $memberList[$k];
                                    unset($memberList[$k]);
                                }
                            }
                            array_unshift($memberList,$first_member);

                            unset($groupInfo['manage']);
                            unset($groupInfo['members']);
                            $list = [
                                'type' => 'getGroupInfo',
                                'data' => [
                                    'groupInfo' => $groupInfo,
                                    'manageList' => $manageList,
                                    'memberList' => $memberList,
                                    'isAdded' => $isAdded,
                                    'isManage' => $isManage,
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
                            $members_arr = explode(',',$members);
                            //权限检查
                            if(!in_array($send_mid,$members_arr)){
                                return null;
                            }
                            //
                            if(!empty($members)){
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
                            //重置成员个人信息
                            foreach ($newIds as $id){
                                $groups = Db::table('df_member')->where('id',$id)->value('groups');
                                $groups_arr = explode(',',$groups);
                                if(!in_array($gid,$groups_arr)){
                                    if(!empty($groups)){
                                        $groups_arr[] = $gid;
                                        $newGroups = implode(',',$groups_arr);
                                    }else{
                                        $newGroups = $gid;
                                    }

                                    Db::table('df_member')->where('id',$id)->setField('groups',$newGroups);
                                }
                            }
                            if($members){
                                //返回响应结果
                                $fds = Db::table('df_socket_client')->where('user_id','in',$newIds)->column('fd');
                                $me_res = json_encode([
                                    'type' => 'addGroupMember',
                                    'data' => [
                                        'code' => 0,
                                        'info' => '添加成功'
                                    ]
                                ],JSON_UNESCAPED_UNICODE);
                                $server->push($frame->fd,$me_res);
                                //向新加入成员推送通知消息
                                $res = json_encode([
                                    'type' => 'addedGroup',
                                    'data' => [
                                        'dataId'=>$gid,
                                        'groupId'=>$gid,
                                        'userId'=>$one['created_mid'],
                                        'name'=> $one['name'],
                                        'firstChar'=>'☆',
                                        'images'=>$one['icon'],
                                        'updateTime'=> uc_time_format($nowTime),
                                        'listType'=>2,
                                        'is_recommend'=>0,
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
                                //向已经加入的聊天列表推送提示 todo
                                //在群聊窗口向群里所有人推送加入群的消息
                                $allMember = Db::table('df_group')
                                    ->where('id',$gid)
                                    ->where('status',1)
                                    ->value('members');
                                $allMember = explode(',',$allMember);
                                $sendMessage = [];

                                foreach ($newIds as $item){
                                    $info = Db::table('df_member')
                                        ->field('id,username,nickname')
                                        ->where('id',$item)
                                        ->find();
                                    $name = !empty($info['nickname']) ? $info['nickname'] : $info['username'];
                                    /*$content = json_encode([
                                        'text' => $name.'加入',
                                        'type' => 'word'
                                    ],JSON_UNESCAPED_UNICODE);*/
                                    $content = $name.'加入';
                                    $message = [
                                        'type' => 1,
                                        'status' => 1,
                                        'content' => $content,
                                        'create_time' => $nowTime,
                                        'update_time' => $nowTime,
                                        'send_time' => $nowTime,
                                        'group_id' => $gid,
                                    ]; //保存消息到数据库
                                    $id = Db::table('df_message_group')->insert($message,false,true,'id');
                                    $sendMessage[] = [
                                        'type' => 1,
                                        'content' => $content,
                                        'id' => $id,
                                    ];
                                }

                                $res = json_encode([
                                    'type' => 'getSystemMessage',
                                    'data' => $sendMessage
                                ],JSON_UNESCAPED_UNICODE);
                                $groupFds = Db::table('df_socket_client')->where('user_id','in',$allMember)->column('fd');
                                foreach ($groupFds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res);
                                    }
                                }

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
                                    //重置成员个人信息
                                    foreach ($userIds as $id){
                                        $groups = Db::table('df_member')->where('id',$id)->value('groups');
                                        $groups_arr = explode(',',$groups);
                                        if(in_array($gid,$groups_arr)){
                                            $groups_arr[] = $gid;
                                            $newGroups = array_diff($groups_arr,[$gid]);
                                            $newGroups = implode(',',$newGroups);
                                            Db::table('df_member')->where('id',$id)->setField('groups',$newGroups);
                                        }
                                    }
                                    if($r){
                                        $data = [
                                            'code' => 0,
                                            'info' => '成功移出群'
                                        ];
                                        //移出群系统消息保存到数据库,并发送
                                        $sendMessage = [];
                                        foreach ($userIds as $uid){
                                            $info = Db::table('df_member')->field('id,username,nickname')->where('id',$uid)->find();
                                            $name = !empty($info['nickname']) ? $info['nickname'] : $info['username'];
                                            /*$content = json_encode([
                                                'text' => $name.'被移出',
                                                'type' => 'word'
                                            ],JSON_UNESCAPED_UNICODE);*/
                                            $content = $name.'被移出';
                                            $message = [
                                                'type' => 1,
                                                'status' => 1,
                                                'content' => $content,
                                                'create_time' => $nowTime,
                                                'update_time' => $nowTime,
                                                'send_time' => $nowTime,
                                                'group_id' => $gid,
                                            ]; //保存消息到数据库
                                            $id = Db::table('df_message_group')->insert($message,false,true,'id');
                                            $sendMessage[] = [
                                                'type' => 1,
                                                'content' => $content,
                                                'id' => $id,
                                            ];

                                        }
                                        $res = json_encode([
                                            'type' => 'getSystemMessage',
                                            'data' => $sendMessage
                                        ],JSON_UNESCAPED_UNICODE);
                                        $server->push($frame->fd,$res);
                                    }
                                }

                            }
                            $me_res = json_encode([
                                'type' => 'delGroupMember',
                                'data' => $data
                            ],JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd,$me_res);

                            break;
                        //处理好友申请
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
                                //保存消息到数据库
                                $content = '我们已经加为好友了，可以开始聊天了！';
                                $message1 = [
                                    'send_mid' => $send_mid,
                                    'to_mid' => $data['user_id'],
                                    'type' => 1,
                                    'status' => 1,
                                    'content' => $content,
                                    'create_time' => $nowTime,
                                    'update_time' => $nowTime,
                                    'send_time' => $nowTime,
                                ];
                                Db::table('df_message')->insert($message1);
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
                                        'updateTime'=> uc_time_format($nowTime),
                                        'listType'=>1,
                                        'type'=>1,
                                        'num'=>1,
                                        'status'=>1,
                                        'msg'=>$f2_nickname.'成为您的好友',
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
                                        'updateTime'=> uc_time_format($nowTime),
                                        'listType'=>1,
                                        'type'=>1,
                                        'num'=>1,
                                        'status'=>1,
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
                        case 'notifyKefu':
                            if(!isset($data['user_id'])){
                                return null;
                            }
                            $kefuIds = Db::table('df_member')->where('is_kefu',1)->column('id');
                            foreach ($kefuIds as $kefuId){
                                $I = Db::table('df_friends')
                                    ->where('friend_mid',$send_mid)
                                    ->where('mid',$kefuId)
                                    ->find();
                                $userInfo = Db::table('df_member')->field('id,username,nickname,signature,avatar')->where('id',$send_mid)->find();

                                $res = json_encode([
                                    'type' => 'addedFriend',
                                    'data' => [
                                        'dataId'=>$I,
                                        'userId'=>$data['user_id'],
                                        'name'=>$userInfo['username'],
                                        'firstChar'=> getFirstChar($userInfo['username']),
                                        'signature'=> $userInfo['signature'],
                                        'images'=>$userInfo['avatar'],
                                        'updateTime'=> uc_time_format($nowTime),
                                        'listType'=>1,
                                        'type'=>1,
                                        'num'=>1,
                                        'status'=>1,
                                        'msg'=>$userInfo['username'].'成为您的好友',
                                    ]
                                ],JSON_UNESCAPED_UNICODE);
                                $fds = Db::table('df_socket_client')->where('user_id',$kefuId)->column('fd');
                                foreach ($fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res);
                                    }
                                }

                            }


                            break;
                        //处理入群申请
                        case 'handleGroupApply':
                            Db::startTrans();
                            try {
                                $to_mid = $data['user_id'];
                                $groupId = $data['groupId'];
                                Db::table('df_apply')
                                    ->where('id',$data['dataId'])
                                    ->update(['status'=>1,'update_time'=>$nowTime]);
                                $one = Db::table('df_group')->field('id,name,members,icon,created_mid')->where('id',$groupId)->find();
                                if(!$one){
                                    return null;
                                }
                                //更新群信息
                                $group_update = $one['members'];
                                if(!empty($one['members'])){
                                    $groupMemberArr = explode(',',$one['members']);
                                    if(!in_array($to_mid,$groupMemberArr)){
                                        $group_update = $one['members'].','.$to_mid;
                                    }
                                }else{
                                    $group_update = $to_mid;
                                }
                                Db::table('df_group')->where('id',$groupId)->setField('members',$group_update);
                                //更新新成员人个信息库
                                $toUserGroups = Db::table('df_member')->where('id',$to_mid)->value('groups');
                                $toUserGroups_update = $toUserGroups;
                                if(!empty($toUserGroups)){
                                    $toUserGroups_arr = explode(',',$toUserGroups);
                                    if(!in_array($one['id'],$toUserGroups_arr)){
                                        $toUserGroups_update = $toUserGroups.','.$one['id'];
                                    }
                                }else{
                                    $toUserGroups_update = $one['id'];
                                }
                                Db::table('df_member')->where('id',$to_mid)->setField('groups',$toUserGroups_update);

                                //向新加入成员推送通知消息
                                $res = json_encode([
                                    'type' => 'addedGroup',
                                    'data' => [
                                        'dataId'=>$one['id'],
                                        'groupId'=>$one['id'],
                                        'userId'=>$one['created_mid'],
                                        'name'=> $one['name'],
                                        'firstChar'=>'☆',
                                        'images'=>$one['icon'],
                                        'updateTime'=> uc_time_format($nowTime),
                                        'listType'=>2,
                                        'is_recommend'=>0,
                                        'type'=>1,
                                        'num'=>1,
                                        'status'=>0,
                                        'msg'=>'你加入了该群',
                                    ]
                                ],JSON_UNESCAPED_UNICODE);
                                $fds = Db::table('df_socket_client')->where('user_id',$to_mid)->column('fd');
                                foreach ($fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res);
                                    }
                                }
                                Db::commit();
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
                                ->order('id','desc')
                                ->select();

                            if(!empty($msg_list)){
                                $readMsgIds = [];
                                foreach ($msg_list as $k=>$v)
                                {
                                    $updateId = Db::table('df_message')
                                        ->where('id',$v['id'])
                                        ->value('id');
                                    Db::table('df_message')
                                        ->where('id',$updateId)
                                        ->where('to_mid',$send_mid)
                                        ->update([
                                        'status' =>3,
                                        'update_time' =>$nowTime,
                                        'read_time' =>$nowTime,
                                    ]);
                                    $readMsgIds[] = $updateId;
                                    //$msg_list[$k]['create_time'] = date('H:i',$v['create_time']);
                                    $msg_list[$k]['create_time'] = uc_time_format($v['create_time']);
                                    $msg_list[$k]['status'] = $v['status'];
                                }

                                //
                                $to_res = [
                                    'type' => 'batchReadMsg',
                                    'data' => $readMsgIds
                                ];
                                $to_res = json_encode($to_res);
                                $to_fds = Db::table('df_socket_client')->where('user_id',$from_mid)->column('fd');
                                foreach ($to_fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$to_res);
                                    }
                                }
                            }else{
                                $msg_list = [];
                            }
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
                            break;//聊天窗口获取消息
                        //群聊天窗口获取消息
                        case 'getGroupMessage':
                            if(!isset($data['group_id'])){
                                return '未获取到参数group_id';
                            }
                            $group_id = $data['group_id'];

                            $lastId = $data['lastId'];//起始ID
                            $where = [
                                ['group_id','=',$group_id],
                            ];
                            if($lastId>0){
                                $where[] = ['id','<',$lastId];
                            }
                            $limit = (isset($data['limit']) && $data['limit']<=15) ? $data['limit'] : 15 ;

                            $msg_list = Db::table('df_message_group')
                                ->where($where)
                                ->limit($limit)
                                ->order('id','desc')
                                ->select();
                            if(!empty($msg_list)){
                                foreach ($msg_list as $k=>$v)
                                {
                                    //读取
                                    $isInserted = Db::table('df_read_group_msg')
                                        ->where('uid',$send_mid)
                                        ->where('group_msg_id',$v['id'])
                                        ->where('status',3)
                                        ->value('id');
                                    if(!$isInserted){
                                        Db::table('df_read_group_msg')->insert([
                                            'uid' => $send_mid,
                                            'group_id' => $v['group_id'],
                                            'group_msg_id' => $v['id'],
                                            'status' => 3,
                                            'create_time' => $nowTime,
                                            'update_time' => $nowTime,
                                        ]);
                                    }

                                    //===============
                                    $msg_list[$k]['create_time'] = uc_time_format($v['create_time']);
                                    if($v['send_mid']>0){
                                        $info = Db::table('df_member')
                                            ->where('id',$v['send_mid'])
                                            ->field('id,username,nickname,avatar')
                                            ->find();
                                        $msg_list[$k]['name'] = !empty($info['nickname']) ? $info['nickname'] : $info['username'];
                                        $msg_list[$k]['avatar'] = $info['avatar'];
                                    }
                                }

                            }else{
                                $msg_list = [];
                            }
                            $res = [
                                'type' => 'getGroupMessage',
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
                        //聊天窗口发送消息
                        case 'sendMsg':
                            $content = $data['content'];
                            $send_mid = $data['user_id'];
                            $to_mid = $data['to_user_id'];
                            //如果不是好友也不能发
                            $exs = Db::table('df_friends')
                                ->where('mid',$send_mid)
                                ->where('friend_mid',$to_mid)
                                ->value('id');
                            if(!$exs){
                                return null;
                            }
                            if($send_mid==$to_mid){
                                return null;
                            }
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
                                $message['update_time'] = uc_time_format($nowTime);
                                $sendUserInfo = Db::table('df_member')
                                    ->where('id',$message['send_mid'])
                                    ->where('status',1)
                                    ->field('id,username,nickname,avatar')
                                    ->find();

                                $one['userId'] = $sendUserInfo['id'];
                                $one['name'] = !empty($sendUserInfo['nickname']) ? $sendUserInfo['nickname'] : $sendUserInfo['username'];;
                                $one['firstChar'] = getFirstChar($one['name']);
                                $one['images'] = $sendUserInfo['avatar'];
                                $one['listType'] = 1;
                                $one['num'] = 1;
                                $one['updateTime'] = uc_time_format($nowTime);
                                $one['msg'] = $message['content'];
                                $one['status'] = 1;
                                $one['type'] = $message['type'];
                                $one['dataId'] = $message_id;
                                $push_to = json_encode([
                                    'type'=>'pushMessage',
                                    'data'=>$one
                                ],JSON_UNESCAPED_UNICODE);
                                //消息保存成功下发给双方
                                $res_to = $res_me = json_encode([
                                    'type'=>'receiveMessage',
                                    'data'=>$one
                                ],JSON_UNESCAPED_UNICODE);

                                $me_fds = Db::table('df_socket_client')->where('user_id',$send_mid)->column('fd');
                                foreach ($me_fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res_me);
                                    }
                                }
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
                        case 'readMsg':
                            if(!isset($data['id']) || !isset($data['to_user_id']) || !isset($data['readEd']) ){
                                return null;
                            }
                            $toMid = $data['to_user_id'];
                            $id = $data['id'];
                            $update = [
                                'status' => 2,
                                'update_time' => $nowTime,
                                'receive_time' => $nowTime,
                            ];
                            if($data['readEd']==1 ){
                                $update['read_time'] = $nowTime;
                                $update['status'] = 3;
                            }
                            $r = Db::table('df_message')
                                ->where('id',$id)
                                ->where('send_mid',$toMid)
                                ->where('to_mid',$send_mid)
                                ->update($update);
                            if($r){
                                $res = [
                                    'type' => 'readMsg',
                                    'data' => [
                                        'id' => $id,
                                        'status' => $update['status']
                                    ]
                                ];
                                $push_to = json_encode($res);
                                $fds = Db::table('df_socket_client')->where('user_id','in',[$toMid,$send_mid])->column('fd');
                                foreach ($fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$push_to);
                                    }
                                }
                            }

                            break;
                        case 'readGroupMsg':
                            if(!isset($data['id']) || !isset($data['readUid']) || !isset($data['readEd']) || !isset($data['group_id']) ){
                                return null;
                            }
                            //读取
                            $isRead = Db::table('df_read_group_msg')
                                ->where('uid',$data['readUid'])
                                ->where('group_msg_id',$data['id'])
                                ->where('status',3)
                                ->value('id');
                            if(!$isRead){
                                Db::table('df_read_group_msg')->insert([
                                    'uid' => $send_mid,
                                    'group_id' => $data['group_id'],
                                    'group_msg_id' => $data['id'],
                                    'status' => 3,
                                    'create_time' => $nowTime,
                                    'update_time' => $nowTime,
                                ]);
                            }
                            break;
                        case 'sendGroupMsg':
                            if(!isset($data['group_id'])){
                                return '未获取到参数group_id';
                            }
                            $content = $data['content'];
                            //$send_mid = $data['user_id'];
                            $group_id = $data['group_id'];

                            //是否是群内成员，不是则禁止发消息
                            $groupFind = Db::table('df_group')->where('id',$group_id)->field('id,members,name')->find();
                            $members = $groupFind['members'];
                            $members = explode(',',$members);
                            if(!in_array($send_mid,$members)){
                                return null;
                            }
                            //保存消息到数据库
                            $message = [
                                'group_id' => $group_id,
                                'send_mid' => $send_mid,
                                'type' => $data['type'],
                                'status' => 1,
                                'content' => $content,
                                'create_time' => $nowTime,
                                'update_time' => $nowTime,
                                'send_time' => $nowTime,
                            ];
                            $message_id = Db::table('df_message_group')->insert($message,false,true,'id');
                            if($message_id>0){
                                $info = Db::table('df_member')->where('id',$send_mid)->field('id,username,nickname,avatar')->find();
                                $message['id'] = $message_id;
                                $message['update_time'] = uc_time_format($nowTime);
                                $message['avatar'] = $info['avatar'];
                                $message['name'] = !empty($info['nickname']) ? $info['nickname'] : $info['username'];

                                $groupFind = Db::table('df_group')->where('id',$group_id)->field('id,members,icon,name')->find();
                                $members = $groupFind['members'];
                                $members = explode(',',$members);
                                $me_fds = Db::table('df_socket_client')->where('user_id','in',$members)->column('fd');

                                //推送至列表
                                $one = [
                                    'dataId'=>$message_id,
                                    'groupId'=>$group_id,
                                    'userId'=>$send_mid,
                                    'name'=> $groupFind['name'],
                                    'sender'=> !empty($info['nickname']) ? $info['nickname'] : $info['username'],
                                    'face'=> $info['avatar'],
                                    'firstChar'=>'☆',
                                    'images'=>$groupFind['icon'],
                                    'updateTime'=> uc_time_format($nowTime),
                                    'listType'=>2,
                                    'is_recommend'=>0,
                                    'type'=>$message['type'],
                                    'num'=>1,
                                    'status'=>1,
                                    'msg'=>$content,
                                ];
                                $push_to = json_encode([
                                    'type'=>'pushGroupMessage',
                                    'data'=>$one
                                ],JSON_UNESCAPED_UNICODE);
                                //消息保存成功下发给所有人
                                $res_me = json_encode([
                                    'type'=>'receiveGroupMessage',
                                    'data'=>$one
                                ],JSON_UNESCAPED_UNICODE);

                                foreach ($me_fds as $fd){
                                    if($server->isEstablished($fd)){
                                        $server->push($fd,$res_me);
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
                                    ->field('id,username,nickname,avatar,is_jianguan')
                                    ->limit(500)
                                    ->select();
                                foreach ($friends as $friend){
                                    $one['userId'] = $friend['id'];
                                    $one['name'] = !empty($friend['nickname']) ? $friend['nickname'] : $friend['username'];
                                    $one['firstChar'] = getFirstChar($one['name']);
                                    $one['images'] = $friend['avatar'];
                                    $one['listType'] = 1;
                                    $one['num'] = 0;
                                    $one['updateTime'] = uc_time_format($nowTime);
                                    $one['msg'] = '';
                                    $one['is_jianguan'] = $friend['is_jianguan'];
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
                                    foreach ($groups as $group){
                                        $one['userId'] = $group['created_mid'];
                                        $one['groupId'] = $group['id'];
                                        $one['name'] = $group['name'];
                                        $one['firstChar'] = '☆';
                                        $one['images'] = $group['icon'];
                                        $one['listType'] = 2;
                                        $one['num'] = 0;
                                        $one['updateTime'] = uc_time_format($nowTime);
                                        $one['msg'] = '';
                                        $one['status'] = 1;
                                        $one['is_jianguan'] = 0;
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
                        case 'setInfo':
                            if(!isset($data['params'])){
                                return null;
                            }
                            $formData = $data['params'];
                            $update = [
                                'nickname' => $formData['nickname'],
                                'signature' => $formData['signature'],
                                'avatar' => $formData['avatar'],
                                'update_time' => $nowTime
                            ];
                            $r = Db::table('df_member')
                                ->where('id',$send_mid)
                                ->where('status',1)
                                ->update($update);

                            if($r){
                                $res = [
                                    'type' => 'setInfo',
                                    'data' => [
                                        'code' => 0,
                                        'info' => 'success'
                                    ]
                                ];
                            }else{
                                $res = [
                                    'type' => 'setInfo',
                                    'data' => [
                                        'code' => -1,
                                        'error' => '设置失败'
                                    ]
                                ];
                            }
                            $res  = json_encode($res,JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd,$res);
                            break;
                        case 'setGroupInfo':
                            if(!isset($data['params'])){
                                return null;
                            }
                            $formData = $data['params'];
                            $update = [
                                'name' => $formData['name'],
                                'icon' => $formData['icon'],
                                'description' => $formData['description'],
                                'update_time' => $nowTime
                            ];
                            $r = Db::table('df_group')
                                ->where('id',$formData['groupId'])
                                ->where('created_mid',$send_mid)
                                ->update($update);
                            if($r){
                                $res = [
                                    'type' => 'setGroupInfo',
                                    'data' => [
                                        'code' => 0,
                                        'info' => 'success'
                                    ]
                                ];
                            }else{
                                $res = [
                                    'type' => 'setGroupInfo',
                                    'data' => [
                                        'code' => -1,
                                        'error' => '设置失败'
                                    ]
                                ];
                            }
                            $res  = json_encode($res,JSON_UNESCAPED_UNICODE);
                            $server->push($frame->fd,$res);
                            break;
                        //删除或退出群
                        case 'outGroup':
                            if(!isset($data['group_id'])){
                                return null;
                            }
                            $group_id = $data['group_id'];
                            $find = Db::table('df_group')->where('id',$group_id)->find();
                            $members = $find['members'];
                            $members_arr = explode(',',$members);
                            //如果是群主
                            if($send_mid==$find['created_mid']){
                                //删除该群所有消息
                                Db::table('df_message_group')
                                    ->where('group_id',$group_id)
                                    ->delete(true);
                                //删除群
                                Db::table('df_group')->where('id',$group_id)->delete(true);
                                //重置所有个人信息
                                foreach ($members_arr as $mid){
                                    $groups = Db::table('df_member')->where('id',$mid)->value('groups');
                                    $groups_arr = explode(',',$groups);
                                    $newGroups_arr = array_diff($groups_arr,[$group_id]);
                                    $newGroups = implode(',',$newGroups_arr);
                                    Db::table('df_member')->where('id',$mid)->setField('groups',$newGroups);
                                }
                            }else{
                                //重置群信息
                                $newMember_arr = array_diff($members_arr,[$send_mid]);
                                $newMember = implode(',',$newMember_arr);
                                Db::table('df_group')->where('id',$group_id)->setField('members',$newMember);
                            }
                            //重置个人信息
                            $groups = Db::table('df_member')->where('id',$send_mid)->value('groups');
                            $groups_arr = explode(',',$groups);
                            $newGroups_arr = array_diff($groups_arr,[$group_id]);
                            $newGroups = implode(',',$newGroups_arr);
                            Db::table('df_member')->where('id',$send_mid)->setField('groups',$newGroups);
                            //推送
                            $res = [
                                'type'=> 'outGroup',
                                'data'=>[
                                    'code'=>0,
                                    'info'=>'success'
                                ]
                            ];
                            $res = json_encode($res);
                            $server->push($frame->fd, $res);
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