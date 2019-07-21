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
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            pp("decode msg error!\n");
            return null;
        }
        $caller = new Caller();
        //默认调用App\WebSocket\Index控制的index方法
        $class = '\\App\\WebSocket\\' . ucfirst(isset($data['class']) && !empty($data['class']) ? $data['class'] : 'Index');
        $caller->setControllerClass($class);

        $action = isset($data['action']) && !empty($data['action']) ? $data['action'] : 'index';
        $caller->setAction($action);
        $param = isset($data['param']) && is_array($data['param']) ? $data['param'] : [];
        $caller->setArgs($param);
        return $caller;
    }

    public function encode(Response $response, $client): ?string
    {
        return $response->getMessage();
    }

}