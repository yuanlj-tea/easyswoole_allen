<?php

namespace App\RoomActor;

use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;

class RoomActor extends AbstractActor
{
    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('room_actor');
    }

    protected function onStart()
    {
        pp(__METHOD__,$this->actorId());
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