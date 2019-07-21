<?php


namespace App\Dispatch\Consume;


use App\Libs\AmqpJob;

class AmqpConsume extends Consume
{
    /**
     * 允许的amqp类型
     * @var
     */
    private $allowAmqpType = [
        AMQP_EX_TYPE_DIRECT,
        AMQP_EX_TYPE_FANOUT,
        AMQP_EX_TYPE_TOPIC,
    ];

    public function consume($argv, ...$params)
    {
        if (
            !isset($argv['exchange']) ||
            !isset($argv['queue']) ||
            !isset($argv['route_key']) ||
            !isset($argv['type'])
        ) {
            throw new \Exception('缺少参数');
        }
        $exchange = $argv['exchange'];
        $type = $argv['exchange'];
        $queue = $argv['queue'];
        $routeKey = $argv['route_key'];
        if (!in_array($type, $this->allowAmqpType)) {
            throw new \Exception('无效的参数：type');
        }
        $tries = $params[0] ?? null;

        go(function () use ($type, $exchange, $queue, $routeKey, $tries) {
            $consumer = new AmqpJob($type, $exchange, $queue, $routeKey, $tries);
            $consumer->dealMq(true);
            $consumer->closeConnetct();
        });


    }

}