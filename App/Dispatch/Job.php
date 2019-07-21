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

use App\Container\Container;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\RedisPool;
use App\Utility\Pool\MysqlPool;
use EasySwoole\EasySwoole\Config;

class Job
{
    private $easyswoole_root;

    /**
     * 注册的命令
     * @var
     */
    private $registerCommand;

    /**
     * di容器对象
     * @var
     */
    private $DI;

    /**
     * 命令行参数
     * @var array
     */
    private $argv;

    public function __construct()
    {
        global $argv;
        $this->easyswoole_root = realpath(__DIR__ . '/../../');
        $this->DI = Container::getInstance();

        //加载console
        $this->registerCommand = $this->registerConsoleCommand();

        //help提示
        if (!isset($argv[1]) || strtolower($argv[1]) == 'help') {
            $this->showHelp();
        }

        //解析参数
        array_shift($argv);
        $argv = $this->parsingArgv($argv);
        $this->argv = $argv;

        //注册连接池
        $this->registerPool();
        //匹配console
        $this->runConsole($argv);

        $driver = $argv['driver'] ?? null;
        $tries = $argv['tries'] ?? null;
        if (empty($driver)) {
            $this->error('缺少driver参数');
            $this->showHelp();
        }
        switch ($driver) {
            case 'database':
                $obj = $this->DI->get(\App\Dispatch\Consume\DatabaseConsume::class);
                $obj->consume($argv, $tries);
                break;
            case 'redis':
                $obj = $this->DI->get(\App\Dispatch\Consume\RedisConsume::class);
                $obj->consume($argv, $tries);
                break;
            case 'amqp':
                $obj = $this->DI->get(\App\Dispatch\Consume\AmqpConsume::class);
                $obj->consume($argv, $tries);
                break;
            case 'nsq':
                $obj = $this->DI->get(\App\Dispatch\Consume\NsqConsume::class);
                $obj->consume($argv, $tries);
                break;
            case 'kafka':
                $obj = $this->DI->get(\App\Dispatch\Consume\KafkaConsume::class);
                $obj->consume($argv, $tries);
                break;
            default:
                $this->error('无效的driver参数');
                break;
        }
    }

    /**
     * 显示帮助提示
     */
    public function showHelp()
    {
        $helpCode = <<<HELP
1、支持队列驱动:redis、database、amqp、nsq、kafka
    
2、queue具体使用参见readme:
    https://github.com/a1554610616/easyswoole_allen/blob/master/App/Dispatch/readme.md
    
3、运行自定义command:
    php Job.php command=foo:bar(命令名)
HELP;
        $this->info($helpCode);

        if (!empty($this->registerCommand)) {
            $promptHtml = <<<HTML
4、已经注册的命令:
HTML;
            $this->info($promptHtml);
            foreach ($this->registerCommand as $k => $v) {
                $this->displayItem($k, sprintf("%s [%s]", $v['command'], $v['desc']));
            }
        }
        die;
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
     * 注册console command
     * @return array
     * @throws Exception
     */
    public function registerConsoleCommand()
    {
        $config = require_once __DIR__ . '/Console/config.php';
        $registerCommand = [];
        foreach ($config as $k => $v) {
            $obj = $this->DI->get($v);
            $command = $obj::getCommand();
            $desc = $obj::getDesc();
            if (in_array($command, $registerCommand)) {
                $this->error(sprintf("[%s] 命令已经被注册", $command));
                die;
            }
            $registerCommand[$v]['command'] = $command;
            $registerCommand[$v]['desc'] = $desc;
        }
        return $registerCommand;
    }

    /**
     * 运行console command
     * @param $argv
     * @throws Exception
     */
    public function runConsole($argv)
    {
        $registerCommand = $this->getRegisterCommand();
        $flip = array_flip($registerCommand);
        if (isset($argv['command']) && in_array($argv['command'], $registerCommand)) {
            $obj = $this->DI->get($flip[$argv['command']]);
            go(function () use ($obj, $argv) {
                $obj->handle($argv);
                die;
            });
            die;
        }
    }

    public function getRegisterCommand()
    {
        $registerCommand = [];
        foreach($this->registerCommand as $k=>$v){
            $registerCommand[$k] = $v['command'];
        }
        return $registerCommand;
    }

    public function displayItem($name, $value)
    {
        pp("\e[32m" . str_pad($name, 30, ' ', STR_PAD_RIGHT) . " : " . "\e[34m" . $value . "\e[0m");
    }

    public function info($info)
    {
        pp(sprintf("\033[0m%s", $info));
    }

    public function error($info)
    {
        pp(sprintf("\033[41m%s", $info));
    }
}

new Job();
