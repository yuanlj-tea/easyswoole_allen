<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/1
 * Time: 11:51 PM
 */

namespace App\Utility\Pool;


use EasySwoole\Component\Pool\AbstractPool;
use EasySwoole\EasySwoole\Config;

class AmqpPool extends AbstractPool
{
    protected function createObject()
    {
        $conf = Config::getInstance()->getConf('AMQP');
        $amqp = new AmqpObject($conf['host'], $conf['port'], $conf['user'], $conf['pwd']);
        return $amqp;
    }

}