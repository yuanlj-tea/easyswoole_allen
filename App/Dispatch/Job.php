<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/4/3
 * Time: 14:54
 */

$file = realpath(__DIR__ . '/../../vendor/autoload.php');

if (file_exists($file)) {
    require_once $file;
} else {
    die("include composer autoload.php fail\n");
}

use Swoole\Coroutine\Redis;
use App\Dispatch\DispatchProvider;
use App\Container\Container;
use EasySwoole\Component\Timer;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\RedisObject;
use App\Utility\Pool\RedisPool;
use App\Utility\Pool\MysqlPool;
use App\Utility\Pool\MysqlObject;
use EasySwoole\EasySwoole\Config;
use Swoole\Coroutine\Channel;
use App\Libs\AmqpJob;

class Job
{
    /**
     * @var bool|string
     */
    private $easyswoole_root;

    /**
     * @var mixed
     */
    private $config;

    /**
     * 任务名,\App\Dispatch\DispatchProvider中属性provider的key
     * @var mixed
     */
    private $job_name;

    /**
     * 允许的amqp类型
     * @var
     */
    private $allowAmqpType = [
        AMQP_EX_TYPE_DIRECT,
        AMQP_EX_TYPE_FANOUT,
        AMQP_EX_TYPE_TOPIC,
    ];

    /**
     * di容器对象
     * @var
     */
    private $DI;

    public function __construct()
    {
        $this->easyswoole_root = realpath(__DIR__ . '/../../');
        $this->config = @require_once $this->easyswoole_root . '/dev.php';

        global $argv;

        //help提示
        if (!isset($argv[1]) || strtolower($argv[1]) == 'help') {
            $this->showHelp();
        }

        //注册连接池
        $this->registerPool();

        //生成数据库队列驱动表
        if ($argv[1] == 'gen_database') {
            $this->generateJobsDatabase();
            return;
        }

        //解析参数
        array_shift($argv);
        $argv = $this->parsingArgv($argv);
        if (!isset($argv['driver'])) {
            echo "缺少参数或参数错误\n";
            $this->showHelp();
        }

        //方式1
        if (isset($argv['class'])) {
            $this->job_name = $argv['class'];
            $this->runClass();
            return;
        }
        $this->DI = Container::getInstance();
        //方式2
        if (isset($argv['driver']) && (isset($argv['queue']) || isset($argv['topic']))) {
            $tries = isset($argv['tries']) ? $argv['tries'] : 3;
            switch ($argv['driver']) {
                case 'amqp':
                    if (!isset($argv['exchange']) || !isset($argv['queue']) || !isset($argv['route_key']) || !isset($argv['type'])) {
                        throw new \Exception("amqp || 缺少参数");
                        if (!in_array($argv['type'], $this->allowAmqpType)) {
                            throw new \Exception("amqp || 无效的类型");
                        }
                    }
                    $this->runAmqp($argv['type'], $argv['exchange'], $argv['queue'], $argv['route_key'], $tries);
                    break;
                case 'nsq':
                    if (!isset($argv['topic']) || !isset($argv['channel'])) {
                        throw new \Exception("nsq || 缺少参数");
                    }
                    $this->runNsq($argv['topic'], $argv['channel'], $tries);
                    break;
                default:
                    if (!isset($argv['driver']) || !isset($argv['queue'])) {
                        throw new \Exception("缺少参数");
                    }
                    $this->runQueue($argv['driver'], $argv['queue'], $tries);
                    break;
            }
        } else {
            echo "缺少参数\n";
            $this->showHelp();
        }


    }

    /**
     * 显示帮助提示
     */
    public function showHelp()
    {
        $helpCode = <<<HELP
1、支持三种队列驱动：redis、database、amqp

2、database驱动需要生成数据表：php Job.php gen_database

3、支持三种消费方式：

    1、php Job.php class=test_job(DispatchProvider中配置的key)
    
    2、php Job.php driver=redis(驱动名) queue=default_queue_name(队列名) tries=0(失败重试次数)
    
    3、(amqp)php Job.php driver=amqp type=topic exchange=topic_logs queue= route_key=*.laravel tries=0
    
HELP;
        die($helpCode . "\n");

    }

    /**
     * 注册mysql&redis连接池
     */
    public function registerPool()
    {
        //加载配置文件
        @Config::getInstance()->loadEnv($this->easyswoole_root . '/dev.php');

        //注册mysql数据库连接池
        PoolManager::getInstance()
            ->register(MysqlPool::class, 30)
            ->setMinObjectNum(5);

        //注册redis连接池
        PoolManager::getInstance()
            ->register(RedisPool::class, 30)
            ->setMinObjectNum(5);
    }

    public function runQueue($driver, $queue, $tries)
    {
        switch ($driver) {
            case 'redis':
                $this->consumeRedis($queue, $tries);
                break;
            case 'database':
                $this->consumeDatabase($queue, $tries);
                break;
            default:
                die("无效的队列驱动：" . $driver);
                break;
        }
    }

    public function runClass()
    {
        $className = $this->checkJobName($this->job_name);
        $queueDriver = $className::getQueueDriver();
        $queueName = $className::getQueueName();

        switch ($queueDriver) {
            case 'redis':
                $this->consumeRedis($queueName);
                break;
            case 'database':
                $this->consumeDatabase($queueName);
                break;
            default:
                die("无效的队列驱动：" . $queueDriver);
                break;
        }

    }

    public function runAmqp($type, $exchangeName, $queueName, $routeKey, $tries = 0)
    {
        go(function () use ($type, $exchangeName, $queueName, $routeKey, $tries) {
            $consumer = new AmqpJob($type, $exchangeName, $queueName, $routeKey, $tries);
            $consumer->dealMq(true);
            $consumer->closeConnetct();
        });
    }

    public function runNsq($topic, $channel, $tries)
    {
        go(function () use ($topic, $channel, $tries) {
            $conf = Config::getInstance()->getConf('NSQ.nsqlookupd');
            $nsq_lookupd = new NsqLookupd($conf); //the nsqlookupd http addr
            $nsq = new Nsq();
            $config = array(
                "topic" => $topic,
                "channel" => $channel,
                "rdy" => 2,                //optional , default 1
                "connect_num" => 1,        //optional , default 1
                "retry_delay_time" => 5000,  //optional, default 0 , if run callback failed, after 5000 msec, message will be retried
                "auto_finish" => true, //default true
            );
            $nsq->subscribe($nsq_lookupd, $config, function ($msg, $bev) {
                $queueData = json_decode($msg->payload, true);
                if (empty($queueData) || !is_array($queueData)) {
                    return;
                }
                $className = $queueData['class_name'];
                $param = $queueData['param'];
                $obj = $this->DI->get($className, $param);
                $tries = isset($tries) ? $tries : $obj->getTries();

                if ($tries > 0) {
                    for ($i = 0; $i < $tries; $i++) {
                        try {
                            $obj->run();
                            break;
                        } catch (\Exception $e) {
                            echo "执行:{$i}次失败 || 失败原因 || {$e->getMessage()}\n";
                        }
                    }
                } else {
                    $obj->run();
                }
            });
        });
    }

    /**
     * 检查任务类是否存在
     * @param $jobName
     * @return mixed
     */
    public function checkJobName($jobName)
    {
        $provider = (new DispatchProvider())->getProvider();

        if (!array_key_exists($jobName, $provider)) {
            die("无效的参数：" . $jobName . "\n");
        }
        if (!class_exists($provider[$jobName])) {
            die("{$provider[$jobName]} 不存在\n");
        }
        return $provider[$jobName];
    }

    /**
     * 消费redis队列数据
     * @param $queueName 队列名
     * @param $tries 重试次数
     */
    public function consumeRedis($queueName, $tries = null)
    {

        go(function () use ($queueName, $tries) {
            RedisPool::invoke(function (RedisObject $redis) use ($queueName, $tries) {
                $queueArr = explode(',', $queueName);
                while (true) {
                    foreach ($queueArr as $k => $queueName) {
                        if ($redis->lSize($queueName) > 0) {
                            echo "队列名：{$queueName}\n";

                            $queueData = $redis->rPop($queueName);
                            if (!is_null($queueData)) {
                                $queueData = json_decode($queueData, 1);

                                if (is_array($queueData)) {
                                    $className = $queueData['class_name'];

                                    $param = $queueData['param'];
                                    $delay = $queueData['delay'];
                                    $addTime = $queueData['add_time'];

                                    $container = Container::getInstance();
                                    $obj = $container->get($className, $param);
                                    $tries = isset($tries) ? $tries : $obj->getTries();

                                    //延时执行
                                    if ($delay > 0) {
                                        $realDelay = ($addTime + $delay / 1000 - time());
                                        if ($realDelay > 0) { //如果没超过delay时间,延迟执行还剩下的秒数
                                            echo "真实延时:{$realDelay}\n";

                                            // after里默认开启了一个协程
                                            // $chanel = new Channel(1);
                                            Timer::getInstance()->after($realDelay * 1000, function () use ($obj, $queueName, $queueData) {
                                                try {
                                                    $obj->run();
                                                } catch (\Exception $e) {
                                                    echo "异常:{$e->getMessage()}\n";
                                                    // $chanel->push(['queue_name' => $queueName, 'queue_data' => $queueData]);
                                                }
                                            });
                                            // $channelData = $chanel->pop();
                                            // if(!empty($channelData)){
                                            //     $this->failerPush($channelData['queue_name'], $channelData['queue_data']);
                                            // }

                                        } else {
                                            echo "已经超过延时时间了,立即执行\n";
                                            //任务里有io阻塞时,不会阻塞消费
                                            // $chanel = new Channel(1);
                                            go(function () use ($obj, $queueName, $queueData) {
                                                try {
                                                    $obj->run();
                                                } catch (\Exception $e) {
                                                    echo "异常:{$e->getMessage()},扔进channel\n";
                                                    // $chanel->push(['queue_name' => $queueName, 'queue_data' => $queueData]);
                                                }
                                            });
                                            //todo 放弃入失败队列,消费后不做channel pop阻塞入失败队列
                                            // $channelData = $chanel->pop(0.5);
                                            // if (!empty($channelData)) {
                                            //     $this->failerPush($channelData['queue_name'], $channelData['queue_data']);
                                            // }
                                        }
                                        continue;
                                    }

                                    //重试机制
                                    if ($tries > 0) {
                                        // $chanel = new Channel(1);
                                        go(function () use ($obj, $tries, $queueName, $queueData) {
                                            $isSucc = false; //是否调用成功标识
                                            for ($i = 0; $i <= $tries; $i++) {
                                                try {
                                                    echo "重新执行:{$i}次\n";
                                                    $obj->run();
                                                    $isSucc = true;
                                                    break;
                                                } catch (\Exception $e) {
                                                    echo "执行:{$i}次失败\n";
                                                }
                                            }
                                            //重试$tries次失败,入失败队列
                                            if (!$isSucc) {
                                                // $chanel->push(['queue_name' => $queueName, 'queue_data' => $queueData]);
                                            }
                                        });

                                        // $channelData = $chanel->pop(0.5);
                                        // if(!empty($channelData)){
                                        //     $this->failerPush($channelData['queue_name'], $channelData['queue_data']);
                                        // }
                                    } else {
                                        //任务里有io阻塞时,不会阻塞消费
                                        // $chanel = new Channel(1);
                                        go(function () use ($obj, $queueName, $queueData) {
                                            try {
                                                $obj->run();
                                                // $chanel->push(null);
                                            } catch (\Exception $e) {
                                                echo "异常:{$e->getMessage()},扔进channel\n";
                                                // $chanel->push(['queue_name' => $queueName, 'queue_data' => $queueData]);
                                            }
                                        });
                                        //todo 放弃入失败队列,消费后不做channel pop阻塞入失败队列
                                        // $channelData = $chanel->pop();
                                        // if (!empty($channelData)) {
                                        //     $this->failerPush($channelData['queue_name'], $channelData['queue_data']);
                                        // }

                                    }
                                }
                            }
                        } else {
                            sleep(1);
                        }
                    }

                }
            });

        });
    }

    /**
     * 消费database数据
     * @param $queueName
     */
    public function consumeDatabase($queueName, $tries = 0)
    {
        go(function () use ($queueName, $tries) {
            MysqlPool::invoke(function (MysqlObject $db) use ($queueName, $tries) {

                $queueArr = explode(',', $queueName);
                while (true) {
                    $db->startTransaction();
                    $res = $db->selectForUpdate(true)->whereIn('queue', $queueArr)->orderBy('id', 'asc')->getOne('jobs', '*');

                    if (!empty($res)) {

                        $queueData = json_decode($res['content'], 1);
                        if (is_array($queueData)) {
                            $className = $queueData['class_name'];

                            $param = $queueData['param'];
                            $delay = $queueData['delay'];
                            $addTime = $queueData['add_time'];

                            $container = Container::getInstance();
                            $obj = $container->get($className, $param);
                            $tries = isset($tries) ? $tries : $obj->getTries();

                            //延时执行
                            if ($delay > 0) {
                                $realDelay = ($addTime + $delay / 1000 - time());
                                echo "real_delay:" . $realDelay . "\n";
                                if ($realDelay > 0) { //如果没超过delay时间,延迟执行还剩下的秒数
                                    echo "真实延时:{$realDelay}后执行\n";
                                    Timer::getInstance()->after($realDelay * 1000, function () use ($obj, $queueName, $queueData, $db, $res) {
                                        try {
                                            $obj->run();
                                        } catch (\Exception $e) {
                                            $this->failedPushDb($queueName, $queueData, 1);
                                        }
                                    });
                                } else {
                                    echo "已经超过延时时间了,立即执行\n";
                                    go(function () use ($obj, $queueName, $queueData, $tries) {
                                        try {
                                            $obj->run();
                                        } catch (\Exception $e) {
                                            $this->failedPushDb($queueName, $queueData, 1);
                                        }
                                    });
                                }
                                $db->where('id', $res['id'], '=')->delete('jobs');
                                $db->commit();
                                continue;
                            }

                            //重试机制
                            if ($tries > 0) {
                                go(function () use ($obj, $tries, $queueName, $queueData) {

                                    $isSucc = false; //是否调用成功标识
                                    for ($i = 0; $i <= $tries; $i++) {
                                        try {
                                            echo "重新执行:{$i}次\n";
                                            $obj->run();
                                            $isSucc = true;
                                            break;
                                        } catch (\Exception $e) {
                                            echo "执行:{$i}次失败\n";
                                        }
                                    }
                                    if (!$isSucc) {
                                        $this->failedPushDb($queueName, $queueData, $tries);
                                    }
                                });
                            } else {
                                //io阻塞时,不会阻塞消费
                                go(function () use ($obj, $queueName, $queueData, $tries) {
                                    try {
                                        $obj->run();
                                    } catch (\Exception $e) {
                                        $this->failedPushDb($queueName, $queueData, 1);
                                    }
                                });
                            }
                        }
                    } else {
                        sleep(1);
                    }

                    $db->where('id', $res['id'], '=')->delete('jobs');
                    $db->commit();
                }
            });

        });
    }

    /**
     * 执行失败的数据,入failed_jobs表
     */
    public function failedPushDb(string $queueName, array $queueData, int $triesTimes = 0)
    {
        MysqlPool::invoke(function (MysqlObject $db) use ($queueName, $queueData, $triesTimes) {
            $db->insert('failed_jobs', [
                'queue' => $queueName,
                'content' => json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'add_time' => date('Y-m-d H:i:s'),
                'try_times' => $triesTimes
            ]);
        });
    }


    /**
     * 执行失败的数据,入失败的队列
     * @param $queueName 队列名
     * @param $queueData 队列数据
     */
    public function failerPush(string $queueName, array $queueData)
    {
        RedisPool::invoke(function (RedisObject $redis) use ($queueName, $queueData) {
            $redis->lPush($queueName . "_failed_job", json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });

        // $redis = new Redis();
        // $redisConf = $this->config['REDIS'];
        // if($redis->connect($redisConf['host'],$redisConf['port'])){
        //     if(!empty($redisConf['auth'])){
        //         $redis->auth($redisConf['auth']);
        //         $redis->lPush($queueName . "_failed_job", json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        //     }
        // }
    }

    /**
     * 解析argv入数组
     */
    public function parsingArgv($argv)
    {
        $arr = [];
        foreach ($argv as $k => $v) {
            parse_str($v, $arr[$k]);
        }
        $arr = $this->merge_array($arr);
        return $arr;
    }

    /**
     * 将二维数组转为一维数组
     * @param $arr
     * @return mixed
     */
    public function merge_array($arr)
    {
        return call_user_func_array('array_merge', $arr);
    }

    /**
     * 生成队列表
     */
    public function generateJobsDatabase()
    {
        $dropJobsSql = "DROP table if exists `jobs`;";
        $sql = "
CREATE TABLE `jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT '对列名',
  `content` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '队列内容,json数据',
  `add_time` datetime NOT NULL COMMENT '添加时间',
  PRIMARY KEY (`id`),
  KEY `queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

        $dropFailedJobsSql = "DROP table if exists `failed_jobs`;";
        $failedSQL = "
CREATE TABLE `failed_jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT '对列名',
  `content` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '队列内容,json数据',
  `add_time` datetime NOT NULL COMMENT '添加时间',
  `try_times` int default null COMMENT '尝试次数',
  PRIMARY KEY (`id`),
  KEY `queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

        $exitStatus = 0;
        go(function () use ($sql, $failedSQL, $dropJobsSql, $dropFailedJobsSql) {
            try {
                MysqlPool::invoke(function (MysqlObject $db) use ($sql, $failedSQL, $dropJobsSql, $dropFailedJobsSql) {
                    if ($db->rawQuery($dropJobsSql)) {
                        echo "删除jobs表成功\n";
                    }
                    if ($db->rawQuery($dropFailedJobsSql)) {
                        echo "删除failed_jobs表成功\n";
                    }
                    if ($db->rawQuery($sql)) {
                        echo "生成jobs表成功\n";
                    }
                    if ($db->rawQuery($failedSQL)) {
                        echo "生成failed_jobs表成功\n";
                    }
                });
                exit("ok\n");
            } catch (\Swoole\ExitException $e) {
                global $exitStatus;
                $exitStatus = $e->getStatus();
            }
        });
        swoole_event_wait();
        exit($exitStatus);
    }
}

new Job();
