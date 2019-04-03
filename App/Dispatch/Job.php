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
use EasySwoole\Mysqli\Mysqli;
use EasySwoole\Mysqli\Config;
use App\Dispatch\DispatchProvider;
use App\Container\Container;
use EasySwoole\Component\Timer;

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

    public function __construct()
    {
        global $argv;
        array_shift($argv);

        $this->easyswoole_root = realpath(__DIR__ . '/../../');
        $this->config = require_once './config.php';
        $this->job_name = array_shift($argv);

        $this->run();
    }

    public function run()
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

    public function consumeRedis($queueName)
    {
        $redisConfig = $this->config['REDIS'];
        go(function () use ($redisConfig, $queueName) {
            $redis = new Redis();
            if ($redis->connect($redisConfig['host'], $redisConfig['port'])) {
                if (!empty($redisConfig['auth'])) {
                    $redis->auth($redisConfig['auth']);
                }
                while (true) {
                    if ($redis->lSize($queueName) > 0) {
                        $queueData = $redis->rPop($queueName);
                        if (!is_null($queueData)) {
                            $queueData = json_decode($queueData, 1);

                            if (is_array($queueData)) {
                                $className = $queueData['class_name'];

                                $param = $queueData['param'];
                                $delay = $queueData['delay'];
                                $addTime = $queueData['add_time'];

                                $container = Container::getInstance();
                                $obj = $container->get($className,$param);
                                $tries = $obj->getTries();

                                if($delay>0){
                                    echo "延时".($delay/1000)."秒\n";
                                    Timer::getInstance()->after($delay,function() use($obj){
                                        $obj->run();
                                        echo "执行了\n";
                                    });
                                    continue;
                                }

                                if($tries > 0){
                                    $isSucc = false; //是否调用成功标识
                                    for($i=0;$i<=$tries;$i++){
                                        try{
                                            echo "重试:".$i;
                                            $obj->run();
                                            $isSucc = true;
                                            break;
                                        }catch (\Exception $e){
                                            $isSucc = false;
                                        }
                                    }

                                    if(!$isSucc){
                                        $redis->lPush($queueName."_failed_job",json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                    }
                                }else{
                                    try{
                                        $obj->run();
                                    }catch(\Exception $e){
                                        $redis->lPush($queueName."_failed_job",json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                    }
                                }
                            }
                        }
                    } else {
                        sleep(1);
                    }
                }
            } else {
                die("redis连接失败");
            }

        });
    }

    public function consumeDatabase($queueName)
    {
        go(function () {
            $mysqlConf = $this->config['MYSQL'];
            $mysqlLiConfig = new Config($mysqlConf);
            $mysqlLi = new Mysqli($mysqlLiConfig);

            // $mysqlLi->connect();

            while (true) {
                $res = $mysqlLi->where('id', 1, '=')->get('t1', null, '*');
                print_r($res);
                echo $mysqlLi->getLastQuery() . "\n";
                sleep(5);
            }
        });
    }
}

new Job();
