<?php

namespace App\RoomActor;

use App\UserActor\UserActor;
use App\UserActor\UserManager;
use App\Utility\Pool\Predis\PredisPool;
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
        if ($msg instanceof RoomCommand) {
            $arg = $msg->getArg();
            switch ($msg->getCommand()) {
                case $msg::LOGIN:
                    $roomRedisKey = $this->redisKey;
                    self::sendToUserMsg($roomRedisKey,$arg);
                    break;
                case $msg::NEW_MSG:
                    $roomRedisKey = $this->redisKey;
                    self::sendToUserMsg($roomRedisKey,$arg);
                    break;
                case $msg::CHANGE_ROOM:
                    $roomRedisKey = $this->redisKey;
                    self::sendToUserMsg($roomRedisKey,$arg);
                    break;
                default:
                    break;
            }
        }
    }

    protected function onExit($arg)
    {
        // TODO: Implement onExit() method.
    }

    protected function onException(\Throwable $throwable)
    {
        // TODO: Implement onException() method.
    }

    /**
     * 通过roomRedisKey获取房间内UserActorId
     * @param $roomRedisKey
     * @return array
     */
    public static function getUserActorIdByRoomRedisKey($roomRedisKey)
    {
        $predis = PredisPool::defer();
        $members = $predis->sMembers($roomRedisKey);

        $arr = [];
        if (is_array($members)) {
            foreach ($members as $v) {
                $info = UserManager::getUserInfo($v);
                if ($info) {
                    $arr[] = $info->getActorId();
                }
            }
            return $arr;
        }
        return $arr;
    }

    public static function sendToUserMsg($roomRedisKey, $pushMsg)
    {
        $userActorId = self::getUserActorIdByRoomRedisKey($roomRedisKey);
        if (sizeof($userActorId) > 0) {
            foreach ($userActorId as $actorId) {
                UserActor::client()->send($actorId, $pushMsg);
            }
        }
    }

}