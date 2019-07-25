<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/1/26
 * Time: 11:52 PM
 */

namespace App\HttpController;

use App\Container\Container;
use App\Dispatch\DispatchHandler\AmqpDispatch;
use App\Dispatch\DispatchHandler\KafkaDispatch;
use App\Dispatch\DispatchHandler\MysqlDispatch;
use App\Dispatch\DispatchHandler\NsqDispatch;
use App\Dispatch\DispatchHandler\RedisDispatch;
use App\Dispatch\Job\TestJob;
use App\Libs\Publisher;
use App\Middleware\CorsMiddleware;
use App\Middleware\ValidateCsrfToken;
use App\Process\HotReload;
use App\Utility\Pool\AmqpObject;
use App\Utility\Pool\AmqpPool;
use App\Utility\Pool\RedisObject;
use App\Utility\Pool\RedisPool;
use EasySwoole\AtomicLimit\AtomicLimit;
use EasySwoole\Component\Di;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\FastCache\Cache;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Utility\SnowFlake;
use EasySwoole\Utility\Str;
use Swlib\Saber;
use Swlib\SaberGM;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Index extends AbstractController
{
    public function index()
    {
        $this->writeJson(200, 'hello world');
    }

    /**
     * 获取csrf_token
     */
    public function getCsrfToken()
    {
        $this->writeJson(200, $this->session()->get('csrf_token'));
    }

    public function upload()
    {
        $request = $this->request();
        $file = $request->getUploadedFile('img')->moveTo('/tmp/test.jpg');
        pp($file);
    }

    public function onRequest(? string $action): ?bool
    {
        //局部要排除中间件的方法
        $this->middlewareExcept = [
            //Index::class . '\getCsrfToken',
        ];
        //要继承的中间件
        $this->middleware = [
            CorsMiddleware::class,
        ];
        return parent::onRequest($action);
    }

    public function rateLimit()
    {
        RedisPool::invoke(function (RedisObject $redis) {
            $key = 'user:1:api_count';
            $limit = 1;

            $check = $redis->exists($key);
            if ($check) {
                $redis->incr($key);
                $count = $redis->get($key);
                if ($count > $limit) {
                    pp('访问太过频繁');
                    return;
                }
            } else {
                $redis->incr($key);
                $redis->expire($key, 2);
            }

            $count = $redis->get($key);
            pp("访问成功,count：{$count}");
        });
    }

    /**
     * 测试向自定义子进程发送数据
     */
    public function testSendMsgToProcess()
    {
        $request = $this->request();
        $str = $request->getQueryParam('str');
        print_r($GLOBALS['hot_reload_process']);
        $GLOBALS['hot_reload_process']->write($str);
        $pid = $GLOBALS['hot_reload_process']->pid;
        echo "子进程pid：{$pid}\n";
    }

    public function testAmqp()
    {
        $request = $this->request();
        $sendMsg = $request->getQueryParam('msg', 'hello world');

        $exchangeName = 'logs';
        $queueName = '';  //queuename为空，type为fanout时，为发布订阅
        $routeKey = 'php.laravel';

        //libs
        // $amqpConf = Config::getInstance()->getConf('AMQP');
        // $publisher = new Publisher($exchangeName, $queueName, $routeKey, AMQP_EX_TYPE_DIRECT, $amqpConf);
        // $publisher->sendMessage($sendMsg);
        // $publisher->closeConnetct();


        //连接池形式
        // fanout:该值会被忽略，因为该类型的交换机会把所有它知道的队列发消息，无差别区别
        // direct:只有精确匹配该路由键的队列，才会发送消息到该队列
        // topic:只有正则匹配到的路由键的队列，才会发送到该队列
        AmqpPool::invoke(function (AmqpObject $amqp) use ($exchangeName, $queueName, $routeKey, $sendMsg) {
            $channel = $amqp->channel();

            $channel->exchange_declare($exchangeName, AMQP_EX_TYPE_FANOUT, false, true, false);
            $channel->queue_declare($queueName, false, true, false, false);

            $msg = new AMQPMessage($sendMsg);
            $channel->basic_publish($msg, $exchangeName, $routeKey);
            pp("send {$sendMsg} ok");
            file_put_contents('/tmp/test.log', $sendMsg . PHP_EOL, FILE_APPEND);

        });


        //DI方式
        /*$amqpConf = Config::getInstance()->getConf('AMQP');
        $amqpConf = array_values($amqpConf);
        $connection = Container::getInstance()->get(AMQPStreamConnection::class, $amqpConf);

        $channel = $connection->channel();
        $channel->queue_declare($exchangeName, false, true, false, false);

        $msg = Container::getInstance()->get(AMQPMessage::class, $sendMsg);
        $channel->basic_publish($msg, '', $routeKey);
        echo "Sent {$sendMsg}\n";

        $channel->close();
        $connection->close();*/

    }

    public function mysqlProduce()
    {
        //对应consume:
        //php Job.php driver=database queue=queue tries=0
        new MysqlDispatch(new TestJob(1, 'foo', ['bar']), 'queue');
        $this->writeJson(200, 'ok');
    }

    public function redisProduce()
    {
        //对应consume:
        //php Job.php driver=redis queue=queue tries=0
        new RedisDispatch(new TestJob(1, 'foo', ['bar']), 'queue');
        $this->writeJson(200, 'ok');
    }

    public function amqpProduce()
    {
        //topic 主题订阅
        //topic：对 key 进行模式匹配，比如 ab* 可以传递到所有 ab* 的 queue
        //对应consume:php Job.php driver=amqp type=topic exchange=topic_logs queue= route_key=*.laravel tries=0
        // $exchangeName = 'topic_logs';
        // $queueName = '';
        // $routeKey = 'php.laravel';
        // $type = AMQP_EX_TYPE_TOPIC;

        //fanout pub/sub
        //fanout：会向相应的 queue 广播
        //对应consume:php Job.php driver=amqp type=fanout exchange=logs queue= route_key=test tries=0
        // $exchangeName = 'logs';
        // $queueName = '';
        // $routeKey = 'test';
        // $type = AMQP_EX_TYPE_FANOUT;

        //direct 一对一,一对多
        //direct：如果 routing key 匹配，那么 Message 就会被传递到相应的 queue
        //对应consume:php Job.php driver=amqp type=direct exchange=direct_logs queue=queue route_key=test tries=0
        $exchangeName = 'direct_logs';
        $queueName = 'queue';
        $routeKey = 'test';
        $type = 'direct';

        new AmqpDispatch(new TestJob(1, 'bar', ['foo']), $type, $exchangeName, $queueName, $routeKey);
        $this->writeJson(200, 'ok');
    }

    public function nsqProduce()
    {
        //对应consume:php Job.php driver=nsq topic=test2 channel=my_channel tries=0
        $topic = 'test2';
        new NsqDispatch(new TestJob(1, 'foo', ['bar']), $topic);
        $this->writeJson(200, 'ok');
        //及时发布
        // $topic = 'test';
        // $endpoint = new \NSQClient\Access\Endpoint('http://127.0.0.1:4161');
        // $message = new \NSQClient\Message\Message('hello world');
        // $result = \NSQClient\Queue::publish($endpoint, $topic, $message);
        // var_dump($result);

        //延时发布
        // $topic = 'test';
        // $endpoint = new \NSQClient\Access\Endpoint('http://127.0.0.1:4161');
        // $message = (new \NSQClient\Message\Message('hello world'))->deferred(5);
        // $result = \NSQClient\Queue::publish($endpoint, $topic, $message);
        // var_dump($result);

        //批量发布
        // $topic = 'test';
        // $endpoint = new \NSQClient\Access\Endpoint('http://127.0.0.1:4161');
        // $message = \NSQClient\Message\Bag::generate(['msg data 1', 'msg data 2']);
        // $result = \NSQClient\Queue::publish($endpoint, $topic, $message);
        // var_dump($result);
    }

    public function kafkaProduce()
    {
        //对应consume:
        //php Job.php driver=kafka topic=test groupId=test
        new KafkaDispatch(new TestJob(1, 'foo', ['bar']), 'test', '');
        $this->writeJson(200, 'ok');
    }

    //库存队列
    private $inventoryQueue = 'inventory_queue';
    //抢购队列
    private $purchaseQueue = 'purchase_queue';

    public function order()
    {
        $request = $this->request();
        $buyNum = (int)$request->getRequestParam('num');
        if ($buyNum <= 0) {
            return $this->writeJson(500, '购买数量必须大于0');
        }
        $succ = $fail = 0;
        RedisPool::invoke(function (RedisObject $redis) use ($buyNum, $succ, $fail) {
            for ($i = 0; $i < $buyNum; $i++) {
                // if($redis->rPop($this->inventoryQueue)){
                //     $succ++;
                //     $redis->rPush($this->purchaseQueue,1);
                // }else{
                //     $fail++;
                // }
                if ($redis->rpoplpush($this->inventoryQueue, $this->purchaseQueue)) {
                    $succ++;
                } else {
                    $fail++;
                }
            }
            return $this->writeJson(200, ['succ' => $succ, 'fail' => $fail]);
        });
    }

    /**
     * 生成库存队列
     */
    public function enQueue()
    {
        RedisPool::invoke(function (RedisObject $redis) {
            $redis->del($this->inventoryQueue);
            for ($i = 0; $i < 100; $i++) {
                $redis->lPush($this->inventoryQueue, 1);
            }
        });
        return $this->writeJson(200, "库存队列生成成功");
    }

    public function testCsp()
    {
        $csp = new \EasySwoole\Component\Csp();
        $csp->add('t1',function (){
            \co::sleep(0.1);
            return 't1 result';
        });
        $csp->add('t2',function (){
            \co::sleep(0.1);
            return 't2 result';
        });

        pp($csp->exec());
    }

    public function testWaitGroup()
    {
        $ret = [];

        $wait = new \EasySwoole\Component\WaitGroup();

        $wait->add();
        go(function ()use($wait,&$ret){
            \co::sleep(0.1);
            $ret[] = time();
            $wait->done();
        });

        $wait->add();
        go(function ()use($wait,&$ret){
            \co::sleep(2);
            $ret[] = time();
            $wait->done();
        });

        $wait->wait();

        pp($ret);
    }

    public function testAtomicLimit()
    {
        if(AtomicLimit::isAllow('api')){
            pp('succ');
        }else{
            pp('请求频繁');
        }
    }
}