<?php


namespace App\RoomActor;


use App\Libs\Predis;
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
     * 通过房间id获取对应actor id
     * @param $roomId
     * @return array
     * @throws \EasySwoole\Component\Pool\Exception\PoolEmpty
     * @throws \EasySwoole\Component\Pool\Exception\PoolException
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
}