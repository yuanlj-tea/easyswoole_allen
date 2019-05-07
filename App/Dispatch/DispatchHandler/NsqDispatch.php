<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/7
 * Time: 6:13 PM
 */

namespace App\Dispatch\DispatchHandler;


use App\Dispatch\Dispatcher;

class NsqDispatch implements DispatchInterface
{
    /**
     * 驱动名
     * @var string
     */
    private $queueDriver = 'nsq';

    /**
     * 话题
     * @var
     */
    private $topic;

    private $delay;

    public function __construct(Dispatcher $dispatcher, string $topic, int $delay = 0)
    {
        $this->topic = $topic;
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
                ->setNsqTopic($this->topic)
                ->setDelay($this->delay);

            $dispatcher->dispatch($dispatcher);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

    }
}