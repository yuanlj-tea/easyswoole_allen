<?php


namespace App\WebSocket;

use App\Libs\Facades\Room;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Socket\AbstractInterface\Controller;

class Index extends Controller
{
    private $serv;

    public function __construct()
    {
        parent::__construct();
        $this->serv = ServerManager::getInstance()->getSwooleServer();
    }

    public function index()
    {
        $param = $this->caller()->getArgs();
        pp($param);
        $fd = $this->caller()->getClient()->getFd();
        switch ($param['type']) {
            case 'login': //登录
                $data = [
                    'task' => 'login',
                    'params' => [
                        'name' => $param['name'],
                        'email' => $param['email']
                    ],
                    'fd' => $fd,
                    'roomid' => $param['roomid']
                ];
                Room::task(json_encode($data));
                break;
            case 'heartbeat': //心跳
                pp("fd:{$fd}");
                $data = [
                    'code' => 7,
                    'type' => 'heartbeat',
                    'msg' => 'ok'
                ];
                $this->serv->push($fd, json_encode($data));
                break;
            default:
                $data = [
                    'code' => 0,
                    'msg' => 'type error'
                ];
                $this->serv->push($fd, json_encode($data));
                break;
        }
    }

}