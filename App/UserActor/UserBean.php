<?php


namespace App\UserActor;


use EasySwoole\Spl\SplBean;

class UserBean extends SplBean
{
    /**
     * fd
     * @var
     */
    protected $fd;

    /**
     * 用户email
     * @var
     */
    protected $name;

    /**
     * actor id
     * @var
     */
    protected $actorId;

    /**
     * 所属房间id
     * @var
     */
    protected $roomId;

    /**
     * name在线状态,0:不在线,1:在线
     * @var
     */
    protected $state;

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setFd(int $fd)
    {
        $this->fd = $fd;
    }

    public function getFd()
    {
        return $this->fd;
    }

    public function setActorId(string $actorId)
    {
        $this->actorId = $actorId;
    }

    public function getActorId()
    {
        return $this->actorId;
    }

    public function setRoomId(int $roomId)
    {
        $this->roomId = $roomId;
    }

    public function getRoomId()
    {
        return $this->roomId;
    }

    public function setState(int $state)
    {
        $this->state = $state;
    }

    public function getState()
    {
        return $this->state;
    }
}