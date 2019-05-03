<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/2
 * Time: 9:08 PM
 */

namespace App\Libs;

use EasySwoole\Component\Timer;
use App\Container\Container;
use EasySwoole\EasySwoole\Config;

class AmqpJob extends Amqp
{
    private $tries = null;

    public function __construct($type, $exchangeName, $queueName, $routeKey, $tries = null)
    {
        $this->tries = $tries;
        $conf = Config::getInstance()->getConf('AMQP');
        parent::__construct($exchangeName, $queueName, $routeKey, $type, $conf);
    }

    public function doProcess($param)
    {
        try {
            $queueData = json_decode($param, true);
            if (empty($queueData) || !is_array($queueData)) {
                return;
            }

            $className = $queueData['class_name'];
            $param = $queueData['param'];
            // $delay = $queueData['delay'];
            // $addTime = $queueData['add_time'];

            $container = Container::getInstance();
            $obj = $container->get($className, $param);
            $tries = isset($this->tries) ? $this->tries : $obj->getTries();

            //错误重试
            if ($tries > 0) {
                for ($i = 0; $i < $tries; $i++) {
                    try {
                        $obj->run();
                        break;
                    } catch (\Exception $e) {
                        echo "执行:{$i}次失败\n";
                    }
                }
            } else {
                $this->run($obj);
            }
        } catch (\Exception $e) {
            pp(sprintf("%s || %s || %s", $e->getFile(), $e->getFile(), $e->getMessage()));
        }

    }

    public function run($obj)
    {
        try {
            $obj->run();
        } catch (\Exception $e) {
            echo "异常：{$e->getMessage()}\n";
        }
    }
}