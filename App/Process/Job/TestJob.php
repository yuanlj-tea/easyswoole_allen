<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/5/5
 * Time: 11:45
 */

namespace App\Process\Job;


use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;

class TestJob extends AbstractAmqp
{
    public function run(array $queueData)
    {
        echo __CLASS__ . " || 执行任务逻辑\n";
        MysqlPool::invoke(function (MysqlObject $db) {
            $res = $db->where('id', 1, '=')->get('tp_article', null, '*');
            // $res = $db->where('id', 1, '=')->get('test', null, '*');
            pp($res);
        });
    }

}