<?php


namespace app\api\controller;



use think\Controller;

class Index extends Controller
{
    public function initialize()
    {
        //跨域头设置
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With');
        header('Access-Control-Allow-Origin: *');
    }

    public function checkToken()
    {

    }

}