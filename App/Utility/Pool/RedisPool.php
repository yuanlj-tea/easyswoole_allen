<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/2
 * Time: 9:11 PM
 */

namespace App\Utility\Pool;

use EasySwoole\EasySwoole\Config;
use EasySwoole\Component\Pool\AbstractPool;

class RedisPool extends AbstractPool
{
    protected function createObject()
    {
        $redis = new RedisObject();
        $conf = Config::getInstance()->getConf('REDIS');
        if ($redis->connect($conf['host'], $conf['port'])) {
            if (!empty($conf['auth'])) {
                $redis->auth($conf['auth']);
            }
            return $redis;
        } else {
            return null;
        }
    }
}