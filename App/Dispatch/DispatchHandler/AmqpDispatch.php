<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/5
 * Time: 11:22 PM
 */

namespace App\Dispatch\DispatchHandler;


use App\Dispatch\Dispatcher;

class AmqpDispatch implements DispatchInterface
{
    /**
     * 驱动名
     * @var string
     */
    private $queueDriver = 'amqp';

    /**
     * 类型
     * @var
     */
    private $type;

    /**
     * exchange名
     * @var
     */
    private $exchange;

    /**
     * 队列名
     * @var
     */
    private $queue;

    /**
     * routeKey名
     * @var
     */
    private $routeKey;

    /**
     * 延迟时间
     * @var int
     */
    private $delay = 0;

    public function __construct(Dispatcher $dispatcher, $type, $exchange, $queue, $routeKey, $delay = 0)
    {
        $this->type = $type;
        $this->exchange = $exchange;
        $this->queue = $queue;
        $this->routeKey = $routeKey;
        $this->delay = $delay;

        try {
            $this->dispatch($dispatcher);
        } catch (\Exception $e) {
            pp($e->getMessage());
        }
    }

    public function dispatch(Dispatcher $dispatcher)
    {
        try {
            $dispatcher->setQueueDriver($this->queueDriver)
                ->setAmqpType($this->type)
                ->setAmqpExchange($this->exchange)
                ->setAmqpQueue($this->queue)
                ->setAmqpRouteKey($this->routeKey)
                ->setDelay($this->delay);

            $dispatcher->dispatch($dispatcher);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}