<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/3/5
 * Time: 11:17
 */

namespace App\HttpController\Pool;


use App\HttpController\Base\Base;
use App\Utility\Pool\RedisObject;
use App\Utility\Pool\RedisPool;
use EasySwoole\Http\Message\Status;
use co;

class RedisInvoke extends Base
{
    public function index()
    {
        try {
            $result = RedisPool::invoke(function (RedisObject $redis) {
                $redis->set('name', 'zs');
                // co::sleep(1);
                $val = $redis->get('name');
                return $val;
            });
            $this->writeJson(200, $result);
        } catch (\Throwable $throwable) {
            $this->writeJson(Status::CODE_BAD_REQUEST, null, $throwable->getMessage());
        }
    }
}