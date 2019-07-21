<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/4/3
 * Time: 11:21
 */

namespace App\Dispatch\Job;

use App\Dispatch\Dispatcher;
use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;
use App\Utility\Pool\RedisPool;

class TestJob extends Dispatcher
{
    /**
     * 重试次数
     * @var int
     */
    protected $tries = 0;

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
        pp('开始执行');

        // \co::sleep(5);
        // echo "sleep结束\n";

        $db = MysqlPool::defer();
        // $db->insert('user',['name'=>'foo']);
        $res = $db->where('id', 1, '=')->get('user', null, '*');
        pp($res);

        pp('执行结束');
        // throw new \Exception("抛出异常");
        file_put_contents("/tmp/TestJob.log", $this->id . ':' . $this->name . ':' . print_r($this->arr, true) . PHP_EOL, FILE_APPEND);
    }
}