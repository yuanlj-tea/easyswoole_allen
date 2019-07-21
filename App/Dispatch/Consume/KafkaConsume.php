<?php


namespace App\Dispatch\Consume;


use App\Container\Container;

class KafkaConsume extends Consume
{
    public function consume($argv, ...$params)
    {
        if (
            !isset($argv['topic']) ||
            !isset($argv['groupId'])
        ) {
            throw new \Exception('缺少参数');
        }
        $groupId = $argv['groupId'];
        $topic = $argv['topic'];

        $config = \Kafka\ConsumerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(10000);
        $config->setMetadataBrokerList('127.0.0.1:9092');
        $config->setGroupId($groupId);
        $config->setBrokerVersion('0.10.0.0');
        $config->setTopics([$topic]);

        go(function () use ($params) {
            $consumer = new \Kafka\Consumer();
            $consumer->start(function ($topic, $part, $message) use ($params) {
                $queueData = json_decode($message['message']['value'], true);
                if (is_array($queueData)) {
                    $className = $queueData['class_name'];
                    $param = $queueData['param'];

                    $container = Container::getInstance();
                    $obj = $container->get($className, $param);
                    $tries = $params[0] ?? $obj->getTries();
                    if ($tries > 0) {
                        for ($i = 0; $i <= $tries; $i++) {
                            try {
                                echo "重新执行:{$i}次\n";
                                $obj->run();
                                break;
                            } catch (\Exception $e) {
                                echo "执行:{$i}次失败\n";
                            }
                        }
                    } else {
                        try {
                            $obj->run();
                        } catch (\Exception $e) {
                            pp(sprintf("[FILE] %s || [LINE] %s || [MSG] %s", $e->getFile(), $e->getLine(), $e->getMessage()));
                        }
                    }
                }
            });
        });
    }
}