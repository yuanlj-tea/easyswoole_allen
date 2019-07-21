<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/4
 * Time: 11:29 PM
 */

namespace App\HttpController\Pool;

use App\HttpController\Base\BaseRedis;

class Redis extends BaseRedis
{
    public function testRedis()
    {
        $redis = $this->getRedis();
        $res = $redis->hGetAll('SSO_SERVER_TOKEN');
        pp($res);
        $redis->set('name','wz');
        $val = $redis->get('name');
        $this->writeJson(200,$val);
    }
}