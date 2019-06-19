<?php

namespace App\UserActor;

use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;

class UserActor extends AbstractActor
{
    /**
     * 客户端连接fd
     * @var
     */
    private $fd;

    /**
     * 用户email
     * @var
     */
    private $userEmail;

    private $actorId;

    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('user_actor');
    }

    protected function onStart()
    {
        // TODO: Implement onStart() method.
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