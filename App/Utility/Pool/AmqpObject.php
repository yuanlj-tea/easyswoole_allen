<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/1
 * Time: 11:51 PM
 */

namespace App\Utility\Pool;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use EasySwoole\Component\Pool\PoolObjectInterface;

class AmqpObject extends AMQPStreamConnection implements PoolObjectInterface
{
    function gc()
    {
        $this->close();
    }

    function objectRestore()
    {
        $this->close();
    }

    function beforeUse(): bool
    {
        $this->reconnect();
        return true;
    }

}