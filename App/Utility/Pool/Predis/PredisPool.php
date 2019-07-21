<?php


namespace App\Utility\Pool\Predis;


use EasySwoole\Component\Pool\AbstractPool;
use EasySwoole\EasySwoole\Config;

class PredisPool extends AbstractPool
{
    protected function createObject()
    {
        $config = Config::getInstance()->getConf('REDIS');
        $redis = new PredisObject($config);
        return $redis;
    }

}