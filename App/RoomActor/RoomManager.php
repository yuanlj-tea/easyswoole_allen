<?php


namespace App\RoomActor;


use App\Libs\Command;
use App\Libs\Predis;
use App\UserActor\UserActor;
use App\UserActor\UserBean;
use App\UserActor\UserManager;
use App\Utility\Pool\Predis\PredisPool;
use EasySwoole\Component\TableManager;
use Swoole\Table;

class RoomManager
{
    private static $roomKey = 'room_list';

    /**
     * 初始化table用来存储房间信息
     */
    public static function init()
    {
        TableManager::getInstance()->add(self::$roomKey, [
            'roomId' => [
                'type' => Table::TYPE_INT,
                'size' => 4
            ],
            'actorId' => [
                'type' => Table::TYPE_STRING,
                'size' => 50
            ],
            'redisKey' => [
                'type' => Table::TYPE_STRING,
                'size' => 50
            ]
        ], 2048);
    }

    public static function getTable(): Table
    {
        return TableManager::getInstance()->get(self::$roomKey);
    }

    /**
     * 添加房间
     * @param RoomBean $roomBean
     */
    public static function addRoom(RoomBean $roomBean)
    {
        self::getTable()->set($roomBean->getRoomId(), $roomBean->toArray());
    }

    /**
     * 根据roomId获取房间ActorId
     * @param $roomId
     * @return RoomBean|null
     */
    public static function getRoomActorIdByRoomId($roomId)
    {
        $ret = self::getTable()->get($roomId);
        if($ret){
            return new RoomBean($ret);
        }
        return null;
    }

    /**
     * 根据roomId获取房间内所有用户信息
     * @param string $roomId
     * @return array
     */
    public static function getUsersByRoomId(string $roomId)
    {
        $ret = self::getTable()->get($roomId);
        if($ret){
            $roomRedisKey = $ret['redisKey'];
            $predis = PredisPool::defer();
            $members = $predis->sMembers($roomRedisKey);
            $arr = [];
            if(is_array($members)){
                foreach($members as $v){
                    $userInfo = $predis->hGet(UserManager::getChatUserKey(),$v);
                    $arr[] = json_decode($userInfo,1);
                }
            }
            return $arr;
        }
        return [];
    }

    /**
     * 用户登录
     * @param $roomId
     * @param $pushMsg
     */
    public static function doLogin($roomId,$pushMsg)
    {
        $roomInfo = self::getRoomActorIdByRoomId($roomId);
        if($roomInfo){
            $command = new RoomCommand();
            $command->setCommand($command::LOGIN);
            $command->setArg($pushMsg);
            RoomActor::client()->send($roomInfo->getActorId(),$command);
        }
    }

    /**
     * 用户发送新消息
     * @param $roomId
     * @param $pushMsg
     */
    public static function sendNewMsg($roomId,$pushMsg)
    {
        $roomInfo = self::getRoomActorIdByRoomId($roomId);
        if($roomInfo){
            $command = new RoomCommand();
            $command->setCommand($command::NEW_MSG);
            $command->setArg($pushMsg);
            RoomActor::client()->send($roomInfo->getActorId(),$command);
        }
    }

    /**
     * 用户切换房间
     * @param $pushMsg
     */
    public static function changeRoom($pushMsg)
    {
        $oldRoomInfo = self::getRoomActorIdByRoomId($pushMsg['data']['oldroomid']);
        if($oldRoomInfo){
            $command = new RoomCommand();
            $command->setCommand($command::CHANGE_ROOM);
            $command->setArg($pushMsg);
            RoomActor::client()->send($oldRoomInfo->getActorId(),$command);
        }
        $newRoomInfo = self::getRoomActorIdByRoomId($pushMsg['data']['roomid']);
        if($newRoomInfo){
            $command = new RoomCommand();
            $command->setCommand($command::CHANGE_ROOM);
            $command->setArg($pushMsg);
            RoomActor::client()->send($newRoomInfo->getActorId(),$command);
        }
    }

    /**
     * 触发onClone
     */
    public static function doLogout($roomId,$pushMsg)
    {
        $command = new Command();
        $command->setCommand($command::ROOM_SEND_LOGOUT);
        $command->setArg($pushMsg);
        UserActor::client()->sendAll($command);
    }

    /**
     * 通过房间id获取对应用户ActorId
     * @param $roomId
     * @return array
     */
    public static function getActorIdByRoomId($roomId)
    {
        $roomRedisKey = "room:".$roomId;
        $predis = PredisPool::defer();
        $members = $predis->sMembers($roomRedisKey);

        $arr = [];
        if(is_array($members)){
            foreach ($members as $v){
                $info = UserManager::getUserInfo($v);
                if($info){
                    $arr[] = $info->getActorId();
                }
            }
            return $arr;
        }
        return $arr;
    }

    /**
     * 给指定房间里所有UserActor发送消息
     * @param $roomId
     */
    public static function sendToRoomMsg($roomId,$pushMsg)
    {
        $roomActorId = self::getActorIdByRoomId($roomId);
        foreach ($roomActorId as $v) {
            UserActor::client()->send($v, $pushMsg);
        }
    }
}