<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/28
 * Time: 11:42 PM
 */

namespace App\WebSocket;

use App\Libs\Facades\Room;
use App\RoomActor\RoomActor;
use App\UserActor\UserManager;
use EasySwoole\EasySwoole\Config;

class WebSocketEvent
{
    public static function onOpen(\swoole_websocket_server $server, \swoole_http_request $request)
    {
        $roomActorStatus = RoomActor::client()->status();
        if (array_sum($roomActorStatus) == 0) {
            $roomsConf = Config::getInstance()->getConf('rooms');
            foreach ($roomsConf as $k => $v) {
                RoomActor::client()->create([
                    'roomId' => $v,
                    'redisKey' => "room:" . $v
                ]);
            }
        }

        pp("[onOpen] fd:{$request->fd}");
        $data = [
            'task' => 'open',
            'fd' => $request->fd
        ];
        UserManager::task(json_encode($data));
    }

    public static function onClose(\swoole_server $server, int $fd, int $reactorId)
    {
        $info = $server->getClientInfo($fd);

        /**
         * 判断此fd 是否是一个有效的 websocket 连接
         * 参见 https://wiki.swoole.com/wiki/page/490.html
         */
        if ($info && $info['websocket_status'] === WEBSOCKET_STATUS_FRAME) {
            /**
             * 判断连接是否是 server 主动关闭
             * 参见 https://wiki.swoole.com/wiki/page/p-event/onClose.html
             */
            if ($reactorId < 0) {
                echo "server close \n";
            } else {
                echo "client close\n";
            }
            
            $data = array(
                'task' => 'logout',
                'fd' => $fd
            );
            UserManager::task(json_encode($data));

            echo "[client closed]{$fd}\n";
        }
    }
}