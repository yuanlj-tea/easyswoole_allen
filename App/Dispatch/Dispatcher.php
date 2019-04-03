<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/4/3
 * Time: 11:18
 */

namespace App\Dispatch;

use App\Utility\Pool\RedisObject;
use App\Utility\Pool\RedisPool;
use PhpParser\Node\Stmt\Static_;

abstract class Dispatcher
{
    /**
     * 重试次数,默认:3
     * @var int
     */
    protected $tries = 3;

    /**
     * 延迟时间,设为0为立即执行任务
     * @var int
     */
    protected $delay = 0;

    /**
     * 指定队列驱动,默认:redis
     * redis or database
     * @var string
     */
    protected static $queueDriver = 'database';

    /**
     * 队列名,默认:default_queue_name
     * @var string
     */
    protected static $queueName = 'default_queue_name';

    /**
     * 获取重试次数
     * @return int
     */
    public function getTries()
    {
        return $this->tries;
    }

    /**
     * 获取队列驱动名
     * @return string
     */
    public static function getQueueDriver()
    {
        return static::$queueDriver;
    }

    /**
     * 设置队列名
     * @param string $queueName
     * @return mixed|void
     */
    public function setQueueName(string $queueName)
    {
        static::$queueName = $queueName;
        return $this;
    }

    /**
     * 获取队列名
     */
    public static function getQueueName()
    {
        return static::$queueName;
    }

    /**
     * 获取delay时长
     * @return int
     */
    public function getDelay()
    {
        return $this->delay;
    }


    /**
     * 设置延迟时间
     * @param int $delayTime
     * @return $this
     */
    public function setDelay(int $delayTime)
    {
        $this->delay = $delayTime;
        return $this;
    }

    /**
     * run
     * @return mixed
     */
    abstract public function run();

    /**
     * 分发消息
     * @param Dispatcher $dispatcher
     * @throws \Exception
     */
    public function dispatch(Dispatcher $dispatcher)
    {
        try {
            $driver = $dispatcher::getQueueDriver();
            $queueName = $dispatcher::getQueueName();
            $delay = $dispatcher->getDelay();

            //存入队列的数据
            $queueData['class_name'] = Static::class;
            $queueData['param'] = [];  //构造函数的参数
            $queueData['add_time'] = date('Y-m-d H:i:s');
            $queueData['delay'] = $delay;

            $ref = new \ReflectionClass(Static::class);

            $constructor = $ref->getConstructor();
            if ($constructor != null) { //如果有构造函数
                $constructorParam = $constructor->getParameters();

                if (!empty($constructorParam)) { //如果构造函数中有参数
                    foreach ($constructorParam as $k => $v) {
                        $paramName = $v->name;
                        $queueData['param'][$k] = $dispatcher->$paramName;
                    }
                }
            }

            $queueJson = json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            switch ($driver) {
                case 'redis':
                    RedisPool::invoke(function (RedisObject $redis) use ($queueName, $queueJson) {
                        $redis->lPush($queueName, $queueJson);
                    });
                    break;
                case 'database':

                    break;
                default:
                    throw new \Exception('不支持的队列驱动：' . $driver);
            }

        } catch (\Exception $e) {
            throw new \Exception(
                'file:' . $e->getFile() . ' line:' . $e->getLine() . ' message:' . $e->getMessage() . ' trace:' . $e->getTraceAsString()
            );
        }

    }
}