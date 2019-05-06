<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33
 */

namespace EasySwoole\EasySwoole;


use App\Container\Container;
use App\Process\AmqpConsume;
use App\Process\HotReload;
use App\Process\Job\TestJob;
use App\Utility\Pool\AmqpPool;
use App\Utility\Pool\MysqlPool;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Di;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\FastCache\Cache;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Utility\File;
use EasySwoole\FastCache\CacheProcess;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class EasySwooleEvent implements Event
{

    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');

        //加载自定义配置
        // self::loadConf();
        $conf = Config::getInstance();//获取配置文件

        //注册mysql数据库连接池
        PoolManager::getInstance()
            ->register(MysqlPool::class, $conf->getConf('MYSQL.POOL_MAX_NUM'))
            ->setMinObjectNum((int)$conf->getConf('MYSQL.POOL_MIN_NUM'));

        //注册redis连接池
        PoolManager::getInstance()
            ->register(RedisPool::class, $conf->getConf('REDIS.POOL_MAX_NUM'))
            ->setMinObjectNum((int)$conf->getConf('REDIS.POOL_MIN_NUM'));

        //注册rabbitmq连接池
        PoolManager::getInstance()
            ->register(AmqpPool::class, $conf->getConf('AMQP.POOL_MAX_NUM'))
            ->setMinObjectNum((int)$conf->getConf('AMQP.POOL_MIN_NUM'));


    }

    public static function mainServerCreate(EventRegister $register)
    {
        //注册onWorkerStart回调事件
        $register->add($register::onWorkerStart, function (\swoole_server $server, int $workerId) {
            //在每个worker进程启动的时候，预创建redis连接池
            if ($server->taskworker == false) {
                //预创建数量,必须小于连接池最大数量
                PoolManager::getInstance()->getPool(RedisPool::class)->preLoad(6);
            }
            // echo "worker:{$workerId} start\n";
        });

        $conf = Config::getInstance();//获取配置文件
        $swooleServer = ServerManager::getInstance()->getSwooleServer();//获取swoole server
        $isDev = Core::getInstance()->isDev();

        if ($isDev) {
            //自适应热重启,虚拟机下可以传入disableInotify => true,强制使用扫描式热重启,规避虚拟机无法监听事件刷新
            $process = (new HotReload('HotReload', ['disableInotify' => false]))->getProcess();
            $GLOBALS['hot_reload_process'] = $process;
            $swooleServer->addProcess($process);
            // $process->write("向子进程写入数据");
        }
        //amqp消费自定义进程
        $arg = [
            'type' => 'direct',
            'exchange' => 'direct_logs',
            'queue' => 'queue',
            'routeKey' => 'test',
            'class' => TestJob::class //要执行的任务类
        ];
        $amqpConsumeProcess = (new AmqpConsume('AmqpConsume', $arg))->getProcess();
        $swooleServer->addProcess($amqpConsumeProcess);

        //fastCache 数据落地方案
        /*Cache::getInstance()->setTickInterval(5 * 1000);
        Cache::getInstance()->setOnTick(function (CacheProcess $cacheProcess) {
            $data = [
                'data'  => $cacheProcess->getSplArray(),
                'queue' => $cacheProcess->getQueueArray()
            ];
            $path = Config::getInstance()->getConf('TEMP_DIR') . '/' . $cacheProcess->getProcessName();
            File::createFile($path, serialize($data));
        });
        Cache::getInstance()->setOnStart(function (CacheProcess $cacheProcess) {
            $path = Config::getInstance()->getConf('TEMP_DIR') . '/' . $cacheProcess->getProcessName();
            if (is_file($path)) {
                $data = unserialize(file_get_contents($path));
                $cacheProcess->setQueueArray($data['queue']);
                $cacheProcess->setSplArray($data['data']);
            }
        });
        Cache::getInstance()->setOnShutdown(function (CacheProcess $cacheProcess) {
            $data = [
                'data'  => $cacheProcess->getSplArray(),
                'queue' => $cacheProcess->getQueueArray()
            ];
            $path = Config::getInstance()->getConf('TEMP_DIR') . '/' . $cacheProcess->getProcessName();
            File::createFile($path, serialize($data));
        });*/
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        $response->withHeader('Content-type', 'application/json;charset=utf-8');
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {

    }

    /**
     * 引用自定义配置文件
     * @throws \Exception
     */
    public static function loadConf()
    {
        $files = File::scanDirectory(EASYSWOOLE_ROOT . '/App/Config');
        if (is_array($files)) {
            foreach ($files['files'] as $file) {
                $fileNameArr = explode('.', $file);
                $fileSuffix = end($fileNameArr);
                if ($fileSuffix == 'php') {
                    Config::getInstance()->loadFile($file);
                }
            }
        }
    }
}