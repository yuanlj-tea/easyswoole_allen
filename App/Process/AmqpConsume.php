<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/5/5
 * Time: 11:20
 */

namespace App\Process;


use App\Process\Job\TestJob;
use EasySwoole\Component\Process\AbstractProcess;

class AmqpConsume extends AbstractProcess
{
    public function run($arg)
    {
        if (!isset($arg['type']) || !isset($arg['exchange']) || !isset($arg['queue']) || !isset($arg['routeKey']) || !isset($arg['class'])) {
            throw new \Exception("缺少参数");
        }
        if (!class_exists($arg['class'])) {
            throw new \Exception("类 :{$arg['class']} || 不存在");
        }

        go(function () use ($arg) {
            $job = new $arg['class']($arg['type'], $arg['exchange'], $arg['queue'], $arg['routeKey']);
            $job->dealMq(false);
            $job->closeConnetct();
        });
    }

    public function onReceive(string $str)
    {
        $class = __CLASS__;
        echo "子进程{$class}接收到消息：{$str}\n";
    }

    public function onShutDown()
    {
        $class = __CLASS__;
        echo "子进程{$class} shutdown\n";
    }
}