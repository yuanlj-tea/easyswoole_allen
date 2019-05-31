<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/31
 * Time: 3:49 AM
 */

namespace App\Process;


use App\Libs\Facades\Room;
use App\Utility\Pool\Predis\PredisPool;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\ServerManager;

class ChatSubscribe extends AbstractProcess
{
    protected function run($arg)
    {
        try {
            $wsServer = ServerManager::getInstance()->getSwooleServer();

            $predis = PredisPool::defer();
            $obj = $predis->getRedis();
            $obj->subscribe(Room::getChanelName(), function ($instance, $channelName, $message) use ($wsServer) {
                $pushMsg = json_decode($message, 1);
                pp(__CLASS__."子进程接收到消息",print_r($pushMsg,true));
                $localIpPort = Room::getIpPort();
                pp($localIpPort, $pushMsg['disfd']['server']);
                if ($pushMsg['disfd']['server'] != $localIpPort) {
                    foreach ($wsServer->connections as $fd) {
                        pp("其它服务器发来的消息:本机fd:" . $fd);
                        $pushMsg['data']['mine'] = 0;
                        $wsServer->push($fd, json_encode($pushMsg));
                    }
                }
                return true;
            });
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}