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
    protected $userEmail;

    /**
     * actor id
     * @var
     */
    protected $actorId;

    public function setUserEmail(string $email)
    {
        $this->userEmail = $email;
    }

    public function getUserEmail()
    {
        return $this->userEmail;
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
}