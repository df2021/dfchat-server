<?php


namespace app\http;


use think\swoole\Server;

class Swoole extends Server
{
    protected $host = '0.0.0.0';
    protected $serverType = 'socket';
    protected $port = 9502;
    protected $option = [
        'worker_num'=> 4,
        'daemonize'	=> false,
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

    public function onMessage($server, $frame)
    {
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        $server->push($frame->fd, "this is server");
    }

    public function onRequest($request, $response)
    {
        $response->end("<h1>Hello Swoole. #" . rand(1000, 9999) . "</h1>");
    }

    public function onClose($ser, $fd)
    {
        echo "client {$fd} closed\n";
    }
}