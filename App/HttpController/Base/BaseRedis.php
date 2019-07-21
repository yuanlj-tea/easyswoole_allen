<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/4
 * Time: 11:20 PM
 */

namespace App\HttpController\Base;


use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\Config;
use Swoole\Coroutine\Redis;

class BaseRedis extends Base
{
    protected $redis;

    public function onRequest(? string $action): ? bool
    {
        $config = Config::getInstance();
        $redisPoolTimeOut = $config->getConf('REDIS.POOL_TIME_OUT');

        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj($redisPoolTimeOut);
        if($redis){
            $this->redis = $redis;
        }else{
            throw new \Exception($this->request()->getUri()->getPath().' error，redis pool is empty');
        }
        return parent::onRequest($action);
    }

    public function gc()
    {
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($this->redis);
        parent::gc();
    }

    public function getRedis(): Redis
    {
        return $this->redis;
    }
}