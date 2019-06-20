<?php


namespace App\UserActor;


use App\Libs\Command;
use App\RoomActor\RoomManager;
use App\Utility\Pool\Predis\PredisPool;
use EasySwoole\Component\TableManager;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;
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
            'name' => [
                'type' => Table::TYPE_STRING,
                'size' => 50
            ],
            'actorId' => [
                'type' => Table::TYPE_STRING,
                'size' => 50
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
        if ($ret) {
            $predis = PredisPool::defer();
            $detailInfo = $predis->hGet(self::$chatUser, $ret['name']);
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
                $wsServer->push($data['fd'], json_encode($pushMsg));
                break;
            case 'login':
                $pushMsg = self::doLogin($data);
                if (!empty($pushMsg)) {
                    self::sendMsg($pushMsg, $data['fd']);
                }
                break;
            case 'new':
                $pushMsg = self::sendNewMsg($data);
                // self::sendToRoomMsg($data['roomid'], $pushMsg);
                self::sendMsg($pushMsg,$data['fd']);
                break;
            case 'change':
                $pushMsg = self::changeRoom($data);
                self::sendMsg($pushMsg, $data['fd']);
                break;
            case 'logout':
                $pushMsg = self::doLogout($data);
                break;
            default:
                break;
        }
    }

    public static function doLogout($data)
    {
        $command = new Command();
        $command->setCommand($command::LOGOUT);
        $command->setArg($data);
        UserActor::client()->sendAll($command);
    }

    /**
     * 对指定房间id的用户actor id发送消息
     */
    public static function sendToRoomMsg(int $roomId, $pushMsg)
    {
        $roomActorId = RoomManager::getActorIdByRoomId($roomId);
        foreach ($roomActorId as $v) {
            UserActor::client()->send($v, $pushMsg);
        }
    }

    public static function sendMsg($pushMsg, $myfd)
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

    /**
     * 发送聊天消息
     * @param $data
     * @return mixed
     */
    public static function sendNewMsg($data)
    {
        $pushMsg['code'] = 2;
        $pushMsg['msg'] = "";
        $pushMsg['data']['roomid'] = $data['roomid'];
        $pushMsg['data']['fd'] = $data['fd'];
        $pushMsg['data']['name'] = $data['params']['name'];
        $pushMsg['data']['avatar'] = $data['params']['avatar'];
        $pushMsg['data']['newmessage'] = escape(htmlspecialchars($data['message']));
        $pushMsg['data']['remains'] = array();
        if ($data['c'] == 'img') {
            $pushMsg['data']['newmessage'] = '<img class="chat-img" onclick="preview(this)" style="display: block; max-width: 120px; max-height: 120px; visibility: visible;" src="' . $pushMsg['data']['newmessage'] . '">';
        } else {
            $emotion = Config::getInstance()->getConf('emotion');
            foreach ($emotion as $_k => $_v) {
                $pushMsg['data']['newmessage'] = str_replace($_k, $_v, $pushMsg['data']['newmessage']);
            }
            $tmp = self::remind($data['roomid'], $pushMsg['data']['newmessage']);

            if ($tmp) {
                $pushMsg['data']['newmessage'] = $tmp['msg'];
                $pushMsg['data']['remains'] = isset($tmp['remains']) ? $tmp['remains'] : [];
            }
            unset($tmp);
        }
        $pushMsg['data']['time'] = date("H:i", time());
        unset($data);
        return $pushMsg;
    }

    public static function remind($roomid, $msg)
    {
        $data = array();
        if ($msg != "") {
            $data['msg'] = $msg;
            //正则匹配出所有@的人来
            $s = preg_match_all('~@(.+?)　~', $msg, $matches);
            if ($s) {
                $m1 = array_unique($matches[0]);
                $m2 = array_unique($matches[1]);

                $users = RoomManager::getUsersByRoomId($roomid);

                $m3 = array();
                foreach ($users as $_k => $_v) {
                    $m3[$_v['name']] = $_v['fd'];
                }
                $i = 0;
                foreach ($m2 as $_k => $_v) {
                    if (array_key_exists($_v, $m3)) {
                        $data['msg'] = str_replace($m1[$_k], '<font color="blue">' . trim($m1[$_k]) . '</font>', $data['msg']);
                        $data['remains'][$i]['fd'] = $m3[$_v];
                        $data['remains'][$i]['name'] = $_v;
                        $i++;
                    }
                }
                unset($users);
                unset($m1, $m2, $m3);
            }
        }
        return $data;
    }

    public static function open(): array
    {
        $pushMsg['code'] = 4;
        $pushMsg['msg'] = 'success';
        $pushMsg['data']['mine'] = 0;
        $pushMsg['data']['rooms'] = self::getRooms();
        $pushMsg['data']['users'] = self::getOnlineUsers();
        return $pushMsg;
    }

    public static function getRooms(): array
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
            if ($userInfo->getState() == 1) {
                $pushMsg['code'] = 8; //用户已在线
                $command = new Command();
                $command->setCommand($command::ALREADY_ONLINE);
                $command->setArg($pushMsg);

                UserActor::client()->send($userInfo->getActorId(), $command);
                return '';
            }
            //用户之前已登录过,重新登录
            $command = new Command();
            $command->setCommand($command::RECONNECT);
            $command->setArg([
                'fd' => $data['fd'],
                'roomId' => $data['roomid'],
                'state' => 1,
            ]);
            UserActor::client()->send($userInfo->getActorId(), $command);
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
        self::joinRoom($data['roomid'], $data['params']['name']);
        return $pushMsg;
    }

    /**
     * 用户加入房间
     * @param string $roomRedisKey
     * @param string $actorId
     */
    public static function joinRoom(string $roomId, string $name)
    {
        $roomRedisKey = "room:" . $roomId;
        $predis = PredisPool::defer();
        $predis->sAdd($roomRedisKey, $name);
    }

    /**
     * 用户退出房间
     * @param $oldRoomId
     * @param $name
     */
    public static function exitRoom($oldRoomId, $name)
    {
        $roomRedisKey = "room:" . $oldRoomId;
        $predis = PredisPool::defer();
        $predis->sRem($roomRedisKey, $name);
    }

    public static function changeRoom($data)
    {
        $pushMsg['code'] = 6;
        $pushMsg['msg'] = '换房成功';

        $res = self::switchRoom($data['oldroomid'], $data['params']['name'], $data['roomid']);
        if ($res) {
            $pushMsg['data']['oldroomid'] = $data['oldroomid'];
            $pushMsg['data']['roomid'] = $data['roomid'];
            $pushMsg['data']['mine'] = 0;
            $pushMsg['data']['fd'] = $data['fd'];
            $pushMsg['data']['name'] = $data['params']['name'];
            $pushMsg['data']['avatar'] = $data['params']['avatar'];
            $pushMsg['data']['time'] = date("H:i", time());
            unset($data);

            $command = new Command();
            $command->setCommand($command::CHANGE_ROOM);
            $command->setArg($pushMsg);
            UserActor::client()->sendAll($command);

            return $pushMsg;
        }
    }

    public static function switchRoom($oldRoomId, $name, $newRoomId)
    {
        //退出老房间
        self::exitRoom($oldRoomId, $name);
        //加入新房间
        self::joinRoom($newRoomId, $name);

        return true;
    }

    public static function cleanData()
    {
        try {
            $predis = PredisPool::defer();
            if ($predis->exists(self::$chatUser)) {
                $predis->del(self::$chatUser);
                pp(sprintf("清理[%s]成功", self::$chatUser));
            }
            $rooms = Config::getInstance()->getConf('rooms');
            foreach ($rooms as $k => $v) {
                $roomKey = sprintf("room:%d", $v);
                if ($predis->exists($roomKey)) {
                    $predis->del($roomKey);
                    pp("清理[{$roomKey}]成功");
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}