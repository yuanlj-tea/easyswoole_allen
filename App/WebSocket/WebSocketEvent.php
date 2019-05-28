<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/28
 * Time: 11:42 PM
 */

namespace App\WebSocket;


class WebSocketEvent
{
    public static function onOpen(\swoole_websocket_server $server,\swoole_http_request $request)
    {
        pp("onOpen");
    }

    public static function onClose(\swoole_server $server,int $fd,int $reactorId)
    {
        
    }
}