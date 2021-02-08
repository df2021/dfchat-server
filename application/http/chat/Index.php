<?php


namespace app\http\chat;


use think\Db;

class Index
{
    protected $server;

    protected $frame;

    protected $data;

    public function __construct($server, $frame)
    {
        $this->server = $server;
        $this->frame = $frame;
        $this->data = $frame->data;
        return $this;
    }

    public function __set($name,$value)
    {
        if($name=='data'){
            $this->data = $value;
        }
    }

    public function __get($name)
    {
        if($name=='data'){
            return $this->data;
        }
        return '';
    }

    public function checkToken()
    {
        if(!empty($this->data)){
            $data = json_decode($this->data,true);
            $this->data = $data;
            if(isset($data['access_token']) && isset($data['action'])){
                $token_json = decryptToken($data['access_token']);
                $token_info = json_decode($token_json,true);
                return Db::table('df_member')->where('id',$token_info['mid'])->value('id');
            }
        }
        return 0;
    }

    public function requestAction()
    {
        /*$action = $this->data['action'];
        $this->$action();*/
    }
}