<?php


namespace App\Dispatch\Consume;


use App\Container\Container;
use App\Utility\Pool\RedisObject;
use App\Utility\Pool\RedisPool;

class RedisConsume extends Consume
{
    public function consume($argv, ...$params)
    {
        if (!isset($argv['queue']) || empty($argv['queue'])) {
            throw new \Exception('缺少参数');
        }
        $queueName = $argv['queue'] ?? '';
        $tries = $params[0] ?? 0;

        go(function () use ($queueName, $tries) {
            RedisPool::invoke(function (RedisObject $redis) use ($queueName, $tries) {
                $queueArr = explode(',', $queueName);
                while (true) {
                    foreach ($queueArr as $k => $queueName) {
                        if ($redis->lSize($queueName) > 0) {
                            echo "队列名：{$queueName}\n";

                            $queueData = $redis->rPop($queueName);
                            if (!is_null($queueData)) {
                                $queueData = json_decode($queueData, 1);

                                if (is_array($queueData)) {
                                    $className = $queueData['class_name'];
                                    $param = $queueData['param'];
                                    $delay = $queueData['delay'];
                                    $addTime = $queueData['add_time'];

                                    $container = Container::getInstance();
                                    $obj = $container->get($className, $param);
                                    $tries = isset($tries) ? $tries : $obj->getTries();

                                    //延时执行
                                    if ($delay > 0) {
                                        $realDelay = ($addTime + $delay / 1000 - time());
                                        if ($realDelay > 0) { //如果没超过delay时间,延迟执行还剩下的秒数
                                            echo "真实延时:{$realDelay}\n";

                                            // after里默认开启了一个协程
                                            // $chanel = new Channel(1);
                                            Timer::getInstance()->after($realDelay * 1000, function () use ($obj, $queueName, $queueData) {
                                                try {
                                                    $obj->run();
                                                } catch (\Exception $e) {
                                                    echo "异常:{$e->getMessage()}\n";
                                                    // $chanel->push(['queue_name' => $queueName, 'queue_data' => $queueData]);
                                                }
                                            });
                                            // $channelData = $chanel->pop();
                                            // if(!empty($channelData)){
                                            //     $this->failerPush($channelData['queue_name'], $channelData['queue_data']);
                                            // }

                                        } else {
                                            echo "已经超过延时时间了,立即执行\n";
                                            //任务里有io阻塞时,不会阻塞消费
                                            // $chanel = new Channel(1);
                                            go(function () use ($obj, $queueName, $queueData) {
                                                try {
                                                    $obj->run();
                                                } catch (\Exception $e) {
                                                    echo "异常:{$e->getMessage()},扔进channel\n";
                                                    // $chanel->push(['queue_name' => $queueName, 'queue_data' => $queueData]);
                                                }
                                            });
                                            //todo 放弃入失败队列,消费后不做channel pop阻塞入失败队列
                                            // $channelData = $chanel->pop(0.5);
                                            // if (!empty($channelData)) {
                                            //     $this->failerPush($channelData['queue_name'], $channelData['queue_data']);
                                            // }
                                        }
                                        continue;
                                    }

                                    //重试机制
                                    if ($tries > 0) {
                                        // $chanel = new Channel(1);
                                        go(function () use ($obj, $tries, $queueName, $queueData) {
                                            $isSucc = false; //是否调用成功标识
                                            for ($i = 0; $i <= $tries; $i++) {
                                                try {
                                                    echo "重新执行:{$i}次\n";
                                                    $obj->run();
                                                    $isSucc = true;
                                                    break;
                                                } catch (\Exception $e) {
                                                    echo "执行:{$i}次失败\n";
                                                }
                                            }
                                            //重试$tries次失败,入失败队列
                                            if (!$isSucc) {
                                                // $chanel->push(['queue_name' => $queueName, 'queue_data' => $queueData]);
                                            }
                                        });

                                        // $channelData = $chanel->pop(0.5);
                                        // if(!empty($channelData)){
                                        //     $this->failerPush($channelData['queue_name'], $channelData['queue_data']);
                                        // }
                                    } else {
                                        //任务里有io阻塞时,不会阻塞消费
                                        // $chanel = new Channel(1);
                                        go(function () use ($obj, $queueName, $queueData) {
                                            try {
                                                $obj->run();
                                                // $chanel->push(null);
                                            } catch (\Exception $e) {
                                                echo "异常:{$e->getMessage()},扔进channel\n";
                                                // $chanel->push(['queue_name' => $queueName, 'queue_data' => $queueData]);
                                            }
                                        });
                                        //放弃入失败队列,消费后不做channel pop阻塞入失败队列
                                        // $channelData = $chanel->pop();
                                        // if (!empty($channelData)) {
                                        //     $this->failerPush($channelData['queue_name'], $channelData['queue_data']);
                                        // }

                                    }
                                }
                            }
                        } else {
                            sleep(1);
                        }
                    }
                }
            });
        });
    }

    /**
     * 执行失败的数据,入失败的队列
     * @param $queueName 队列名
     * @param $queueData 队列数据
     */
    public function failerPush(string $queueName, array $queueData)
    {
        RedisPool::invoke(function (RedisObject $redis) use ($queueName, $queueData) {
            $redis->lPush($queueName . "_failed_job", json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });
    }
}