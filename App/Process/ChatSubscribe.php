<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/31
 * Time: 3:49 AM
 */

namespace App\Process;


use App\Libs\Facades\Room;
use App\Libs\Predis;
use App\Utility\Pool\Predis\PredisPool;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;

class ChatSubscribe extends AbstractProcess
{
    protected function run($arg)
    {
        try {
            ini_set('default_socket_timeout', -1);
            $wsServer = ServerManager::getInstance()->getSwooleServer();

            $config = Config::getInstance()->getConf('REDIS');
            $predis = new Predis($config);
            $predis->getRedis()->subscribe([Room::getChanelName()], function ($instance, $channelName, $message) use ($wsServer) {
                $pushMsg = json_decode($message, 1);
                $localIpPort = Room::getIpPort();
                pp(sprintf("[local ip port] %s [remote ip port] %s", $localIpPort, $pushMsg['disfd']['server']));
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