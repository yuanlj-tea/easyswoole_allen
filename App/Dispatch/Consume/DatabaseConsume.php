<?php


namespace App\Dispatch\Consume;


use App\Container\Container;
use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;

class DatabaseConsume extends Consume
{
    public function consume($argv, ...$params)
    {
        if (!isset($argv['queue']) || empty($argv['queue'])) {
            throw new \Exception('缺少参数');
        }
        $queueName = $argv['queue'] ?? '';
        $tries = $params[0] ?? null;

        go(function () use ($queueName, $tries) {
            MysqlPool::invoke(function (MysqlObject $db) use ($queueName, $tries) {

                $queueArr = explode(',', $queueName);
                while (true) {
                    $db->startTransaction();
                    $res = $db->selectForUpdate(true)->whereIn('queue', $queueArr)->orderBy('id', 'asc')->getOne('jobs', '*');

                    if (!empty($res)) {

                        $queueData = json_decode($res['content'], 1);
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
                                echo "real_delay:" . $realDelay . "\n";
                                if ($realDelay > 0) { //如果没超过delay时间,延迟执行还剩下的秒数
                                    echo "真实延时:{$realDelay}后执行\n";
                                    Timer::getInstance()->after($realDelay * 1000, function () use ($obj, $queueName, $queueData, $db, $res) {
                                        try {
                                            $obj->run();
                                        } catch (\Exception $e) {
                                            $this->failedPushDb($queueName, $queueData, 1);
                                        }
                                    });
                                } else {
                                    echo "已经超过延时时间了,立即执行\n";
                                    go(function () use ($obj, $queueName, $queueData, $tries) {
                                        try {
                                            $obj->run();
                                        } catch (\Exception $e) {
                                            $this->failedPushDb($queueName, $queueData, 1);
                                        }
                                    });
                                }
                                $db->where('id', $res['id'], '=')->delete('jobs');
                                $db->commit();
                                continue;
                            }

                            //重试机制
                            if ($tries > 0) {
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
                                    if (!$isSucc) {
                                        $this->failedPushDb($queueName, $queueData, $tries);
                                    }
                                });
                            } else {
                                //io阻塞时,不会阻塞消费
                                go(function () use ($obj, $queueName, $queueData, $tries) {
                                    try {
                                        $obj->run();
                                    } catch (\Exception $e) {
                                        $this->failedPushDb($queueName, $queueData, 1);
                                    }
                                });
                            }
                        }
                    } else {
                        sleep(1);
                    }

                    $db->where('id', $res['id'], '=')->delete('jobs');
                    $db->commit();
                }
            });
        });
    }

    /**
     * 执行失败的数据,入failed_jobs表
     */
    public function failedPushDb(string $queueName, array $queueData, int $triesTimes = 0)
    {
        MysqlPool::invoke(function (MysqlObject $db) use ($queueName, $queueData, $triesTimes) {
            $db->insert('failed_jobs', [
                'queue' => $queueName,
                'content' => json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'add_time' => date('Y-m-d H:i:s'),
                'try_times' => $triesTimes
            ]);
        });
    }
}