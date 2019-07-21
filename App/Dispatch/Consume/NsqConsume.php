<?php


namespace App\Dispatch\Consume;


class NsqConsume extends Consume
{
    public function consume($argv, ...$params)
    {
        if(
            !isset($argv['topic']) ||
            !isset($argv['channel'])
        ){
            throw new \Exception('缺少参数');
        }
        $topic = $argv['topic'] ?? '';
        $channel = $argv['channel'] ?? '';
        $tries = $params[0] ?? null;

        go(function () use ($topic, $channel, $tries) {
            $conf = Config::getInstance()->getConf('NSQ.nsqlookupd');
            $nsq_lookupd = new NsqLookupd($conf); //the nsqlookupd http addr
            $nsq = new Nsq();
            $config = array(
                "topic" => $topic,
                "channel" => $channel,
                "rdy" => 2,                //optional , default 1
                "connect_num" => 1,        //optional , default 1
                "retry_delay_time" => 5000,  //optional, default 0 , if run callback failed, after 5000 msec, message will be retried
                "auto_finish" => true, //default true
            );
            $nsq->subscribe($nsq_lookupd, $config, function ($msg, $bev) {
                $queueData = json_decode($msg->payload, true);
                if (empty($queueData) || !is_array($queueData)) {
                    return;
                }
                $className = $queueData['class_name'];
                $param = $queueData['param'];
                $obj = $this->DI->get($className, $param);
                $tries = isset($tries) ? $tries : $obj->getTries();

                if ($tries > 0) {
                    for ($i = 0; $i < $tries; $i++) {
                        try {
                            $obj->run();
                            break;
                        } catch (\Exception $e) {
                            echo "执行:{$i}次失败 || 失败原因 || {$e->getMessage()}\n";
                        }
                    }
                } else {
                    $obj->run();
                }
            });
        });
    }

}