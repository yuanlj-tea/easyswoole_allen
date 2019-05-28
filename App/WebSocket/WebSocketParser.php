<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/28
 * Time: 11:14 PM
 */

namespace App\WebSocket;

use EasySwoole\Socket\AbstractInterface\ParserInterface;
use EasySwoole\Socket\Bean\Caller;
use EasySwoole\Socket\Bean\Response;

class WebSocketParser implements ParserInterface
{
    public function decode($raw, $client): ?Caller
    {
        // TODO: Implement decode() method.
    }

    public function encode(Response $response, $client): ?string
    {
        // TODO: Implement encode() method.
    }

}