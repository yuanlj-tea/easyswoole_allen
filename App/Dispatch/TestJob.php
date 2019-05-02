<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/4/3
 * Time: 11:21
 */

namespace App\Dispatch;

use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;
use App\Utility\Pool\RedisPool;

class TestJob extends Dispatcher
{
    /**
     * 重试次数
     * @var int
     */
    protected $tries = 5;

    /**
     * 指定队列驱动
     * @var string
     */
    protected static $queueDriver = 'redis';

    protected static $queueName = 'default_queue_name';

    protected $delay = 0;

    public $id;

    public $name;

    public $arr;

    public function __construct(int $id, string $name, array $arr)
    {
        $this->id = $id;
        $this->name = $name;
        $this->arr = $arr;
    }


    public function run()
    {
        echo "执行了\n";

        // \co::sleep(5);
        // echo "sleep结束\n";

        MysqlPool::invoke(function (MysqlObject $db) {
            // $res = $db->where('id', 1, '=')->get('tp_article', null, '*');
            $res = $db->where('id', 1, '=')->get('test', null, '*');
            pp($res);
        });

        echo "执行结束\n";

        // throw new \Exception("抛出异常");
        file_put_contents("/tmp/TestJob.log", $this->id . ':' . $this->name . ':' . print_r($this->arr, true) . PHP_EOL, FILE_APPEND);
    }
}