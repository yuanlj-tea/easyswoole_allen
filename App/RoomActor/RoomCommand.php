<?php


namespace App\RoomActor;


use EasySwoole\Spl\SplBean;

class RoomCommand extends SplBean
{

    /**
     * 用户登录
     */
    const LOGIN = 1;

    /**
     * 用户发送新消息
     */
    const NEW_MSG = 2;

    /**
     * 用户切换房间
     */
    const CHANGE_ROOM = 3;

    /**
     * 触发onClose，退出
     */
    const LOG_OUT =4;

    protected $command;

    protected $arg;

    /**
     * @return mixed
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param mixed $command
     */
    public function setCommand($command): void
    {
        $this->command = $command;
    }

    /**
     * @return mixed
     */
    public function getArg()
    {
        return $this->arg;
    }

    /**
     * @param mixed $arg
     */
    public function setArg($arg): void
    {
        $this->arg = $arg;
    }
}