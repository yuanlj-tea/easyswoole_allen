<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/4/3
 * Time: 11:18
 */

namespace App\Dispatch;

use App\Libs\Publisher;
use App\Utility\Pool\AmqpObject;
use App\Utility\Pool\AmqpPool;
use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;
use App\Utility\Pool\RedisObject;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\Config;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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
    protected static $queueDriver = 'redis';

    /**
     * 队列名,默认:default_queue_name
     * @var string
     */
    protected static $queueName = 'default_queue_name';

    /**
     * amqp驱动名
     * @var string
     */
    private $amqp = 'amqp';

    /**
     * 交换机名称,queue driver为amqp时有效
     * @var
     */
    protected static $amqpExchange;

    /**
     * 队列名,queue driver为amqp时有效
     * @var
     */
    protected static $amqpQueue;

    /**
     * 路由键名,queue driver为amqp时有效
     * @var
     */
    protected static $amqpRoutekey;

    /**
     * amqp类型
     * @var
     */
    protected static $amqpType;

    /**
     * NSQ驱动时的参数,话题
     * @var
     */
    protected static $nsqTopic;

    /**
     * 允许的amqp类型
     * @var
     */
    private static $allowAmqpType = [
        // AMQP_EX_TYPE_DIRECT,
        // AMQP_EX_TYPE_FANOUT,
        // AMQP_EX_TYPE_TOPIC,
        'direct',
        'fanout',
        'topic'
    ];

    /**
     * 允许设置的队列驱动
     * @var array
     */
    private $allowDriver = ['redis', 'database', 'amqp', 'nsq'];

    /**
     * 获取重试次数
     * @return int
     */
    public function getTries()
    {
        return $this->tries;
    }

    /**
     * 设置队列驱动
     * @param string $driver
     * @return $this
     */
    public function setQueueDriver(string $driver)
    {
        if (!in_array($driver, $this->allowDriver))
            throw new \Exception("无效的队列驱动");
        static::$queueDriver = $driver;
        return $this;
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
        if (static::$queueDriver == $this->amqp) {
            throw new \Exception("amqp不允许设置此参数");
        }
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
     * 设置amqp的类型,direct/fanout/topic
     * @param string $amqpType
     * @return $this
     * @throws \Exception
     */
    public function setAmqpType(string $amqpType = 'direct')
    {
        if (static::$queueDriver != 'amqp') {
            throw new \Exception("只有amqp类型,才能设置此参数");
        }
        if (!in_array($amqpType, self::$allowAmqpType)) {
            throw new \Exception("无效的类型");
        }
        static::$amqpType = $amqpType;
        return $this;
    }

    /**
     * 设置交换机
     * @param string $exchangeName
     */
    public function setAmqpExchange(string $exchangeName)
    {
        if (static::$queueDriver != 'amqp') {
            throw new \Exception("只有amqp类型,才能设置此参数");
        }
        static::$amqpExchange = $exchangeName;
        return $this;
    }

    /**
     * 设置amqp队列
     * @param string $queueName
     */
    public function setAmqpQueue(string $queueName = '')
    {
        if (static::$queueDriver != 'amqp') {
            throw new \Exception("只有amqp类型,才能设置此参数");
        }
        static::$amqpQueue = $queueName;
        return $this;
    }

    /**
     * 设置amqp路由键
     * @param string $routeKey
     */
    public function setAmqpRouteKey(string $routeKey)
    {
        if (static::$queueDriver != 'amqp') {
            throw new \Exception("只有amqp类型,才能设置此参数");
        }
        static::$amqpRoutekey = $routeKey;
        return $this;
    }

    /**
     * 设置NSQ驱动的topic
     */
    public function setNsqTopic(string $topic)
    {
        if(static::$queueDriver != 'nsq'){
            throw new \Exception("只有nsq驱动,才能设置此参数");
        }
        static::$nsqTopic = $topic;
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

            if ($driver == $this->amqp) {
                $amqpExchange = static::$amqpExchange;
                $amqpQueue = static::$amqpQueue;
                $amqpRouteKey = static::$amqpRoutekey;
                $amqpType = static::$amqpType;
            } else {
                $queueName = $dispatcher::getQueueName();
            }
            $delay = $dispatcher->getDelay();

            //存入队列的数据
            $queueData['class_name'] = Static::class;
            $queueData['param'] = [];  //构造函数的参数
            $queueData['add_time'] = time();
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
                    MysqlPool::invoke(function (MysqlObject $db) use ($queueName, $queueJson) {
                        $db->insert('jobs', [
                            'queue' => $queueName,
                            'content' => $queueJson,
                            'add_time' => date('Y-m-d H:i:s')
                        ]);
                    });
                    break;
                case $this->amqp:
                    //连接池
                    /*AmqpPool::invoke(function (AmqpObject $amqp) use ($amqpType, $amqpExchange, $amqpQueue, $amqpRouteKey, $queueJson) {
                        $channel = $amqp->channel();

                        $channel->exchange_declare($amqpExchange, $amqpType, false, true, false);
                        $channel->queue_declare($amqpQueue, false, true, false, false);

                        $msg = new AMQPMessage($queueJson);
                        $channel->basic_publish($msg, $amqpExchange, $amqpRouteKey);
                    });*/

                    $amqpConf = Config::getInstance()->getConf('AMQP');
                    if ($delay > 0) {
                        Timer::getInstance()->after($delay * 1000, function () use ($amqpExchange, $amqpQueue, $amqpRouteKey, $amqpType, $amqpConf, $queueJson) {
                            $publisher = new Publisher($amqpExchange, $amqpQueue, $amqpRouteKey, $amqpType, $amqpConf);
                            $publisher->sendMessage($queueJson);
                            $publisher->closeConnetct();
                        });
                    } else {
                        $publisher = new Publisher($amqpExchange, $amqpQueue, $amqpRouteKey, $amqpType, $amqpConf);
                        $publisher->sendMessage($queueJson);
                        $publisher->closeConnetct();
                    }

                    break;
                case 'nsq':
                    $config = Config::getInstance()->getConf('NSQ.nsqlookupd');
                    $topic = static::$nsqTopic;
                    if ($delay > 0) {
                        $endpoint = new \NSQClient\Access\Endpoint($config);
                        $message = (new \NSQClient\Message\Message($queueJson))->deferred(5);
                        $result = \NSQClient\Queue::publish($endpoint, $topic, $message);
                    } else {
                        $endpoint = new \NSQClient\Access\Endpoint($config);
                        $message = new \NSQClient\Message\Message($queueJson);
                        $result = \NSQClient\Queue::publish($endpoint, $topic, $message);
                    }
                    break;
                default:
                    throw new \Exception('不支持的队列驱动：' . $driver);
            }

        } catch (\Exception $e) {
            throw new \Exception(
                sprintf("FILE:%s || LINE:%s || MSG:%s", $e->getFile(), $e->getLine(), $e->getMessage())
            );
        }

    }
}