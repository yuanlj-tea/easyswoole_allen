<?php


namespace App\Libs;


use EasySwoole\Spl\SplBean;

class Command extends SplBean
{
    /**
     * 用户重新登录连接
     */
    const RECONNECT = 1;

    const WS_MSG = 2;

    const REPLY_MSG = 3;

    /**
     * 用户退出登录
     */
    const LOGOUT = 4;

    /**
     * 用户切换房间
     */
    const CHANGE_ROOM = 5;

    /**
     * onOpen事件
     */
    const OPEN = 0;

    /**
     * 用户已在线
     */
    const ALREADY_ONLINE = 6;

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