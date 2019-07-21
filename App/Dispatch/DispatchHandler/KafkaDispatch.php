<?php


namespace App\Dispatch\DispatchHandler;


use App\Dispatch\Dispatcher;

class KafkaDispatch implements DispatchInterface
{
    private $topic;

    private $key;

    private $driver = 'kafka';

    public function __construct(Dispatcher $dispatcher, $topic, $key)
    {
        $this->topic = $topic;
        $this->key = $key;
        $this->dispatch($dispatcher);
    }

    public function dispatch(Dispatcher $dispatcher)
    {
        $dispatcher->setQueueDriver($this->driver)
            ->setKafkatopic($this->topic)
            ->setKafkaKey($this->key);
        $dispatcher->dispatch($dispatcher);
    }

    public static function buildData($topic, $value, $key)
    {
        return [
            'topic' => $topic,
            'value' => $value,
            'key' => $key
        ];
    }
}