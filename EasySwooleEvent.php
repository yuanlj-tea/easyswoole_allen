<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33
 */

namespace EasySwoole\EasySwoole;


use App\Process\HotReload;
use App\Utility\Pool\MysqlPool;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\FastCache\Cache;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Utility\File;
use EasySwoole\FastCache\CacheProcess;

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
            ->register(MysqlPool::class,$conf->getConf('MYSQL.POOL_MAX_NUM'))
            ->setMinObjectNum((int)$conf->getConf('MYSQL.POOL_MIN_NUM'));

        //注册redis连接池
        PoolManager::getInstance()
            ->register(RedisPool::class,$conf->getConf('REDIS.POOL_MAX_NUM'))
            ->setMinObjectNum((int)$conf->getConf('REDIS.POOL_MIN_NUM'));


    }

    public static function mainServerCreate(EventRegister $register)
    {

        $conf = Config::getInstance();//获取配置文件
        $swooleServer = ServerManager::getInstance()->getSwooleServer();//获取swoole server

        $isDev = Core::getInstance()->isDev();
        if ($isDev) {
            //自适应热重启,虚拟机下可以传入disableInotify => true,强制使用扫描式热重启,规避虚拟机无法监听事件刷新
            $swooleServer->addProcess((new HotReload('HotReload', ['disableInotify' => false]))->getProcess());
        }

        /**
         * fastCache 数据落地方案
         */
        Cache::getInstance()->setTickInterval(5 * 1000);
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
        });
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        $response->withHeader('Content-type','application/json;charset=utf-8');
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