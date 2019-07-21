<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/2
 * Time: 9:10 PM
 */

namespace App\Utility\Pool;

use EasySwoole\Component\Pool\PoolObjectInterface;
use Swoole\Coroutine\Redis;

class RedisObject extends Redis implements PoolObjectInterface
{
    public function gc()
    {
        $this->close();
    }

    public function objectRestore()
    {
        // echo "objectRestore\n";
    }

    public function beforeUse(): bool
    {
        return true;
    }
}