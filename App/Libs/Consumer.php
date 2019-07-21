<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/2
 * Time: 12:38 PM
 */

namespace App\Libs;


$file = realpath(__DIR__ . '/../../vendor/autoload.php');
if (file_exists($file)) {
    require_once $file;
} else {
    die("include composer autoload.php fail\n");
}

class Consumer extends Amqp
{

    public function __construct()
    {
        $conf = [
            'host' => '127.0.0.1',  //ip
            'port' => '5672',       //端口号
            'user' => 'guest',      //用户
            'pwd' => 'guest',       //密码
            'vhost' => '/'          //虚拟host
        ];
        // $exchangeName = 'logs';
        // $queueName = 'queue2';
        // $routeKey = 'hello';
        // parent::__construct($exchangeName, $queueName, $routeKey, AMQP_EX_TYPE_FANOUT, $conf);

        $exchangeName = 'topic_logs';
        $queueName = '';  //queuename为空，type为fanout时，为发布订阅
        $routeKey = '*.laravel';
        parent::__construct($exchangeName, $queueName, $routeKey, AMQP_EX_TYPE_TOPIC, $conf);
    }

    public function doProcess($param)
    {
        echo $param . "\n";
    }

}

$consumer = new Consumer();
//$consumer->dealMq(false);
$consumer->dealMq(true);