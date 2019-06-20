<?php


namespace App\UserActor;


use App\RoomActor\RoomManager;
use App\Utility\Pool\Predis\PredisObject;
use App\Utility\Pool\Predis\PredisPool;
use EasySwoole\Component\TableManager;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Utility\SnowFlake;
use Swoole\Table;

class UserManager
{
    private static $userKey = 'user_list';

    /**
     * 登录后的用户对应的name hash key,存储:name => 用户json数据
     * @var string
     */
    private static $chatUser = 'chat_user';

    public static function getChatUserKey()
    {
        return self::$chatUser;
    }

    public static function init()
    {
        TableManager::getInstance()->add(self::$userKey, [
            'fd' => [
                'type' => Table::TYPE_INT,
                'size' => 4
            ],
            'name' => [
                'type' => Table::TYPE_STRING,
                'size' => 50
            ],
            'actorId' => [
                'type' => Table::TYPE_STRING,
                'size' => 50
            ],
            'roomId' => [
                'type' => Table::TYPE_INT,
                'size' => 4
            ],
            'state' => [
                'type' => Table::TYPE_INT,
                'size' => 1
            ],
        ], 2048);
    }

    public static function getTable(): Table
    {
        return TableManager::getInstance()->get(self::$userKey);
    }

    /**
     * 添加用户
     * @param UserBean $userBean
     */
    public static function addUser(UserBean $userBean)
    {
        self::getTable()->set($userBean->getName(), $userBean->toArray());
    }

    /**
     * 获取用户信息
     * @param string $userEmail
     */
    public static function getUserInfo(string $name)
    {
        $ret = self::getTable()->get($name);
        if ($ret) {
            return new UserBean($ret);
        }
        return null;
    }

    public static function getUserDetailInfo(string $name)
    {
        $ret = self::getTable()->get($name);
        if($ret){
            $predis = PredisPool::defer();
            $detailInfo = $predis->hGet(self::$chatUser,$ret['name']);
            return $detailInfo;
        }
        return null;
    }

    /**
     * 更新用户信息
     * @param string $name
     * @param array $data
     */
    public static function updateUserInfo(string $name, array $data)
    {
        self::getTable()->set($name, $data);
    }

    public static function task(string $jsonStr)
    {
        $wsServer = ServerManager::getInstance()->getSwooleServer();

        $data = json_decode($jsonStr, true);
        if (empty($data) || !isset($data['task'])) {
            return;
        }

        switch ($data['task']) {
            case 'open':
                $pushMsg = self::open();
                $wsServer->push($data['fd'],json_encode($pushMsg));
                break;
            case 'login':
                $pushMsg = self::doLogin($data);
                /*$roomActorId = RoomManager::getActorIdByRoomId(1);
                foreach ($roomActorId as $v){
                    UserActor::client()->send($v,$pushMsg);
                }*/
                self::sendMsg($pushMsg,$data['fd']);
                break;
            default:
                break;
        }
    }

    public static function sendMsg($pushMsg, int $myfd)
    {
        $wsServer = ServerManager::getInstance()->getSwooleServer();
        foreach ($wsServer->connections as $fd) {
            if ($fd == $myfd) {
                $pushMsg['data']['mine'] = 1;
            } else {
                $pushMsg['data']['mine'] = 0;
            }
            $wsServer->push($fd, json_encode($pushMsg));
        }
    }

    public static function open() :array
    {
        $pushMsg['code'] = 4;
        $pushMsg['msg'] = 'success';
        $pushMsg['data']['mine'] = 0;
        $pushMsg['data']['rooms'] = self::getRooms();
        $pushMsg['data']['users'] = self::getOnlineUsers();
        return $pushMsg;
    }

    public static function getRooms() :array
    {
        $rooms = Config::getInstance()->getConf('rooms');
        $roomArr = [];
        foreach ($rooms as $k => $v) {
            $roomArr[] = [
                'roomid' => $k,
                'roomname' => $v,
            ];
        }
        return $roomArr;
    }

    public static function getOnlineUsers()
    {
        $rooms = Config::getInstance()->getConf('rooms');
        $arr = [];
        foreach ($rooms as $k => $v) {
            //每个房间对应的用户信息
            $arr[$v] = RoomManager::getUsersByRoomId($v);
        }
        return $arr;
    }

    public static function doLogin($data)
    {
        $userInfo = self::getUserInfo($data['params']['name']);

        $pushMsg['code'] = 1;
        $pushMsg['msg'] = $data['params']['name'] . "加入了群聊";
        $clientDomain = Config::getInstance()->getConf('CLIENT_DOMAIN');
        $pushMsg['data']['roomid'] = $data['roomid'];
        $pushMsg['data']['fd'] = $data['fd'];
        $pushMsg['data']['name'] = $data['params']['name'];
        $pushMsg['data']['email'] = $data['params']['email'];
        $avatar = $clientDomain . '/static/images/avatar/f1/f_' . rand(1, 12) . '.jpg';
        if ($userInfo) {
            $predis = PredisPool::defer();
            $detailInfo = $predis->hGet(self::$chatUser, $userInfo->getName());
            $detailInfo = json_decode($detailInfo, 1);
            $avatar = $detailInfo['avatar'];
        }
        $pushMsg['data']['avatar'] = $avatar;
        $pushMsg['data']['time'] = date("Y-m-d H:i:s", time());

        if ($userInfo) {
            if($userInfo->getState() == 1){
                $pushMsg['code'] = 8; //用户已在线
            }
            //用户之前已登录过,重新登录
            self::updateUserInfo($data['params']['name'], [
                'fd' => $data['fd']
            ]);
        } else {
            $predis = PredisPool::defer();
            $predis->hSet(self::$chatUser, $data['params']['name'], json_encode($pushMsg['data']));
            //用户第一次登录,创建用户actor
            UserActor::client()->create([
                'name' => $data['params']['name'],
                'fd' => $data['fd'],
                'roomId' => $data['roomid'],
                'state' => 1
            ]);
        }
        self::joinRoom($data['roomid'],$data['params']['name']);

        return $pushMsg;
    }

    /**
     * 用户加入房间
     * @param string $roomRedisKey
     * @param string $actorId
     */
    public static function joinRoom(string $roomId, string $name)
    {
        $roomRedisKey = "room:".$roomId;
        $predis = PredisPool::defer();
        $predis->sAdd($roomRedisKey, $name);
    }
}