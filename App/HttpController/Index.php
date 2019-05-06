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
use App\Dispatch\TestJob;
use App\Libs\Publisher;
use App\Middleware\CorsMiddleware;
use App\Middleware\ValidateCsrfToken;
use App\Process\HotReload;
use App\Utility\Pool\AmqpObject;
use App\Utility\Pool\AmqpPool;
use App\Utility\Pool\RedisObject;
use App\Utility\Pool\RedisPool;
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

class Index extends Controller
{
    public function index()
    {

        $job = (new TestJob(1, 'hello', ['hehe']))->setDelay(0 * 1000)->setQueueDriver('redis')->setQueueName("hehe");
        // $job = (new TestJob(1,'hello',['hehe']));
        $job->dispatch($job);
        echo "ok\n";
        $this->writeJson(200, 'ok');


        /*RedisPool::invoke(function (RedisObject $redis){
            $key = 'user:1:api_count';
            $limit = 1;

            $check = $redis->exists($key);
            if($check){
                $redis->incr($key);
                $count = $redis->get($key);
                if($count>$limit){
                    pp('访问太过频繁');
                    return;
                }
            }else{
                $redis->incr($key);
                $redis->expire($key,2);
            }

            $count = $redis->get($key);
            pp("访问成功,count：{$count}");
        });*/


        /*$request = $this->request();
        $response = $this->response();

        TaskManager::async(function (){
            pp("异步任务");
        });

        $this->writeJson(200, 'hello world');*/
    }

    /**
     * 获取csrf_token
     */
    public function getCsrfToken()
    {
        $this->writeJson(200, $this->session()->get('csrf_token'));
    }

    public function testSaber()
    {
        [$html] = SaberGM::list([
            'uri' => [
                'http://www.blog.com/sso/server/login'
            ]
        ]);
        var_dump($html->getParsedDomObject()->getElementsByTagName('button')->item(0)->getAttribute('id'));
        // var_dump($html->getParsedHtml()->getElementsByTagName('input')->item(0)->textContent);
        $this->response()->write('ok');
        return;
        [$json, $xml, $html] = SaberGM::list([
            'uri' => [
                'http://httpbin.org/get',
                'http://www.w3school.com.cn/example/xmle/note.xml',
                'http://httpbin.org/html'
            ]
        ]);
        var_dump($json->getParsedJson());
        var_dump($json->getParsedJsonObject());
        var_dump($xml->getParsedXml());
        var_dump($html->getParsedHtml()->getElementsByTagName('h1')->item(0)->textContent);
        //测试自动登录
        $session = Saber::session([
            'base_uri' => 'http://www.blog.com',
            'redirect' => 0
        ]);
        $session->post('/sso/server/test');
        $res = $session->get('/sso/server/test');
        echo $res->body;
        $this->response()->write($res->body);

        /*$session = Saber::session([
            'base_uri' => 'http://www.blog.com',
            'redirect' => 0
        ]);
        $redirect_url = base64_encode('hehe');
        $res=$session->post('/sso/server/login?redirect_url='.$redirect_url,['user'=>'demo','pwd'=>'demo']);

        echo $res->body;

        $getRes = $session->get('/sso/server/login')->body;
        echo $getRes;*/

    }

    public function upload()
    {
        $request = $this->request();
        $file = $request->getUploadedFile('img')->moveTo('/tmp/test.jpg');
        p($file);
    }

    public function onRequest(? string $action): ?bool
    {
        //局部要排除中间件的方法
        $this->middlewareExcept = [
            Index::class . '\getCsrfToken',
        ];
        //要继承的中间件
        $this->middleware = [
            CorsMiddleware::class,
            ValidateCsrfToken::class,
        ];
        return parent::onRequest($action);
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

    public function testAmqpTopic()
    {
        //topic 主题订阅
        //对应consume:php Job.php driver=amqp type=topic exchange=topic_logs queue= route_key=*.laravel tries=0
        // $exchangeName = 'topic_logs';
        // $queueName = '';
        // $routeKey = 'php.laravel';
        // $type = AMQP_EX_TYPE_TOPIC;

        //fanout pub/sub
        //对应consume:php Job.php driver=amqp type=fanout exchange=logs queue= route_key=test tries=0
        // $exchangeName = 'logs';
        // $queueName = '';
        // $routeKey = 'test';
        // $type = AMQP_EX_TYPE_FANOUT;

        //direct 一对一,一对多
        //对应consume:php Job.php driver=amqp type=direct exchange=direct_logs queue=queue route_key=test tries=0
        $exchangeName = 'direct_logs';
        $queueName = 'queue';
        $routeKey = 'test';
        $type = AMQP_EX_TYPE_DIRECT;

        new AmqpDispatch(new TestJob(1,'bar',['foo']),$type,$exchangeName,$queueName,$routeKey);

        // $job = (new TestJob(1, 'bar', ['foo']))
        //     ->setQueueDriver('amqp')
        //     ->setDelay(5)
        //     ->setAmqpType($type)
        //     ->setAmqpExchange($exchangeName)
        //     ->setAmqpQueue($queueName)
        //     ->setAmqpRouteKey($routeKey);
        // $job->dispatch($job);
        $this->writeJson(200, 'ok');

        $request = $this->request();
        $sendMsg = $request->getQueryParam('msg', 'hello world');

        // AmqpPool::invoke(function (AmqpObject $amqp) use ($exchangeName, $queueName, $routeKey, $sendMsg) {
        //     $channel = $amqp->channel();
        //
        //     $channel->exchange_declare($exchangeName, AMQP_EX_TYPE_TOPIC, false, true, false);
        //     $channel->queue_declare($queueName, false, true, false, false);
        //
        //     $msg = new AMQPMessage($sendMsg);
        //     $channel->basic_publish($msg, $exchangeName, $routeKey);
        //
        //     pp("send {$sendMsg} ok");
        //     file_put_contents('/tmp/test.log', $sendMsg . PHP_EOL, FILE_APPEND);
        //
        // });
    }

    public function testNsq()
    {
        //及时发布
        $topic = 'test';
        $endpoint = new \NSQClient\Access\Endpoint('http://127.0.0.1:4161');
        $message = new \NSQClient\Message\Message('hello world');
        $result = \NSQClient\Queue::publish($endpoint, $topic, $message);
        var_dump($result);

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
}