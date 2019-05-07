<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/7
 * Time: 9:12 AM
 */

namespace App\Dispatch\DispatchHandler;


use App\Dispatch\Dispatcher;

class MysqlDispatch implements DispatchInterface
{
    /**
     * 驱动名
     * @var string
     */
    private $queueDriver = 'database';

    /**
     * 队列名
     * @var
     */
    private $queue;

    private $delay = 0;

    public function __construct(Dispatcher $dispatcher, string $queue, int $delay = 0)
    {
        $this->queue = $queue;
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
                ->setQueueName($this->queue)
                ->setDelay($this->delay);

            $dispatcher->dispatch($dispatcher);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

}