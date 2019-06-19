<?php


namespace App\RoomActor;


use EasySwoole\Spl\SplBean;

class RoomBean extends SplBean
{
    protected $actorId;

    protected $roomId;

    protected $redisKey;

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

    public function setRedisKey(string $redisKey)
    {
        $this->redisKey;
    }

    public function getRedisKey()
    {
        return $this->redisKey;
    }
}