<?php


namespace App\WebSocket;

use App\Utility\Pool\Predis\PredisObject;
use App\Utility\Pool\Predis\PredisPool;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Utility\SnowFlake;

class Room
{
    /**
     * 房间ID与客户端连接ID对应关系redis hash key,存储:roomid => disFd
     * @var string
     */
    private $rfMap = 'rfMap';

    /**
     * 登录后的用户对应的user_id hash key,存储:user_id => 用户json数据
     * @var string
     */
    private $chatUser = 'chat_user';

    /**
     * 登录的用户id和name的hash key,存储:disFd => userid
     * @var string
     */
    private $fdToUserId = 'fd_to_userid';

    /**
     * 统一接收数据,发送数据
     * @param string $jsonStr
     */
    public function task(string $jsonStr)
    {
        $pushMsg = ['code' => 0, 'msg' => '', 'data' => []];
        $wsServer = ServerManager::getInstance()->getSwooleServer();

        $data = json_decode($jsonStr, true);
        if (empty($data)) {
            return;
        }
        switch ($data['task']) {
            case 'open':
                $pushMsg = $this->open();
                $wsServer->push($data['fd'], json_encode($pushMsg));
                break;
            case 'login':
                $this->doLogin($data);
                break;
            default:
                break;
        }
    }

    /**
     * 客户端连接上ws时,服务端onOpen事件调用,返回房间及房间内用户信息
     * @return mixed
     */
    public function open(): array
    {
        $pushMsg['code'] = 4;
        $pushMsg['msg'] = 'success';
        $pushMsg['data']['mine'] = 0;
        $pushMsg['data']['rooms'] = $this->getRooms();
        $pushMsg['data']['users'] = $this->getOnlineUsers();
        return $pushMsg;
    }

    /**
     * 获取房间信息
     * @return array
     */
    public function getRooms(): array
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

    /**
     * 获取每个房间的在线客户端
     * @return array
     */
    public function getOnlineUsers(): array
    {
        $rooms = Config::getInstance()->getConf('rooms');
        $arr = [];
        foreach ($rooms as $k => $v) {
            //每个房间对应的用户信息
            $arr[$v] = $this->getUsersByRoom($v);
        }
        return $arr;
    }

    /**
     * 获取制定房间里的用户信息
     * @param $roomId
     * @return mixed
     * @throws \EasySwoole\Component\Pool\Exception\PoolEmpty
     * @throws \EasySwoole\Component\Pool\Exception\PoolException
     * @throws \Throwable
     */
    public function getUsersByRoom($roomId): array
    {
        $roomKey = "room:{$roomId}";
        $res = PredisPool::invoke(function (PredisObject $predis) use ($roomKey) {
            $users = $predis->hVals($roomKey);
            $arr = [];
            if (is_array($users)) {
                foreach ($users as $k => $v) {
                    $userInfo = $predis->hGet($this->chatUser, $v);
                    $arr[] = json_decode($userInfo, true);
                }
            }
            return $arr;
        });
        return $res;
    }

    public function doLogin($data)
    {
        $pushMsg['code'] = 1;
        $pushMsg['msg'] = $data['params']['name'] . "加入了群聊";

        $pushMsg['data']['roomid'] = $data['roomid'];
        $pushMsg['data']['fd'] = $data['fd'];
        $pushMsg['data']['name'] = $data['params']['name'];
        $pushMsg['data']['avatar'] = DOMAIN . '/static/images/avatar/f1/f_' . rand(1, 12) . '.jpg';
        $pushMsg['data']['time'] = date("Y-m-d H:i:s", time());

        //将新登录的用户存入redis hash
        $userId = SnowFlake::make(rand(0, 31), rand(0, 31));
        pp("生成的userid ||" . $userId);

        $res = PredisPool::invoke(function (PredisObject $predis) use ($userId, $pushMsg) {
            $predis->hSet($this->chatUser, $userId, json_encode($pushMsg['data']));

            $disFd = $this->getDistributeFd($pushMsg['data']['fd']);
            $predis->hSet($this->fdToUserId, $disFd, $userId);

            //加入房间
            $this->joinRoom($pushMsg['data']['roomid'], $pushMsg['data']['fd']);

            return $pushMsg;
        });
        self::getRedis()->hSet(self::$chatUser, $userId, json_encode($pushMsg['data']));

        $disFd = self::getDistributeFd($data['fd']);
        self::getRedis()->hSet(self::$fdToUserId, $disFd, $userId);
        //加入房间
        self::joinRoom($data['roomid'], $data['fd']);

        return $pushMsg;
    }

    public function getDistributeFd(int $fd): string
    {
        $ipPort = $this->getIpPort();
        $data = [
            'server' => $ipPort,
            'fd' => $fd
        ];
        return json_encode($data);
    }

    /**
     * 获取本机ip和ws开启的port
     * @return string
     */
    public function getIpPort(): string
    {
        $ip = getLocalIp();
        $port = Config::getInstance()->getConf('MAIN_SERVER.PORT');
        return sprintf("%s:%s", $ip, $port);
    }

    public function joinRoom($roomId, int $fd)
    {
        $userId = $this->getUserId($fd);
    }

    /**
     * 根据fd获取用户id
     * @param int $fd
     */
    public function getUserId(int $fd)
    {
        PredisPool::defer();
        $disFd = $this->getDistributeFd($fd);
        $userId = PredisPool::invoke(function (PredisObject $predis) use ($disFd) {

        });
    }
}