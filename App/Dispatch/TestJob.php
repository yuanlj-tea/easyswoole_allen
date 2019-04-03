<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/4/3
 * Time: 11:21
 */

namespace App\Dispatch;

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

    protected static $queueName = 'default_queue_name1';

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
        // throw new \Exception("错了");
        file_put_contents("/tmp/TestJob.log", $this->id . ':' . $this->name . ':' . print_r($this->arr, true) . PHP_EOL, FILE_APPEND);
    }
}