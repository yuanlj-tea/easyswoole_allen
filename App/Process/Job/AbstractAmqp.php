<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/5/5
 * Time: 11:34
 */

namespace App\Process\Job;

use App\Libs\Amqp;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;

abstract class AbstractAmqp extends Amqp
{
    public function __construct($type, $exchange, $queue, $routeKey)
    {
        $conf = Config::getInstance()->getConf('AMQP');
        parent::__construct($exchange, $queue, $routeKey, $type, $conf);
    }

    public function doProcess($param)
    {
        $queueData = json_decode($param, true);
        if(!is_array($queueData) || empty($queueData)){
            return;
        }
        // $this->run($queueData);
        TaskManager::processAsync($this->run($queueData));
    }

    abstract public function run(array $queueData);
}