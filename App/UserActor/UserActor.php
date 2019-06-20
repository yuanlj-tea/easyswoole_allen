<?php

namespace App\UserActor;

use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;
use EasySwoole\EasySwoole\ServerManager;

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
    private $name;

    private $roomId;

    private $state;

    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('user_actor');
    }

    protected function onStart()
    {
        $this->name = $this->getArg()['name'];
        $this->fd = $this->getArg()['fd'];
        $this->roomId = $this->getArg()['roomId'];
        $this->state = $this->getArg()['state'];
        UserManager::addUser(new UserBean([
            'name' => $this->name,
            'fd' => $this->fd,
            'roomId' => $this->roomId,
            'actorId' => $this->actorId(),
            'state' => $this->state
        ]));

        pp(sprintf("[UserActor创建成功][name]%s[actorId]%s", $this->name, $this->actorId()));
    }

    protected function onMessage($msg)
    {
        ServerManager::getInstance()->getSwooleServer()->push($msg['data']['fd'],json_encode($msg));
        if(!isset($msg['task'])){
            return;
        }
        switch($msg['task']){
            case 'login':

                break;
            default:
                break;
        }
    }

    protected function onExit($arg)
    {

    }

    protected function onException(\Throwable $throwable)
    {

    }


}