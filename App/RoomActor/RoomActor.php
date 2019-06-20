<?php

namespace App\RoomActor;

use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;
use EasySwoole\EasySwoole\ServerManager;

class RoomActor extends AbstractActor
{
    private $roomId;

    private $redisKey;

    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('room_actor');
    }

    protected function onStart()
    {
        $this->roomId = $this->getArg()['roomId'];
        $this->redisKey = $this->getArg()['redisKey'];
        pp(sprintf("[RoomActor创建成功][roomId]%d[actorId]%s", $this->roomId, $this->actorId()));

        RoomManager::addRoom(new RoomBean([
            'actorId' => $this->actorId(),
            'roomId' => $this->roomId,
            'redisKey' => $this->redisKey
        ]));
    }

    protected function onMessage($msg)
    {
        // TODO: Implement onMessage() method.
    }

    protected function onExit($arg)
    {
        // TODO: Implement onExit() method.
    }

    protected function onException(\Throwable $throwable)
    {
        // TODO: Implement onException() method.
    }

}