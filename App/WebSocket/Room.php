<?php


namespace App\WebSocket;

use App\Libs\Predis;
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
     * 服务器间通信的发布订阅channel名
     * @var string
     */
    private $channel_name = 'send_msg_channel';

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
            case 'open':  //onOpen事件调用
                $pushMsg = $this->open();
                $wsServer->push($data['fd'], json_encode($pushMsg));
                break;
            case 'login': //登录
                $pushMsg = $this->doLogin($data);
                break;
            case 'new': //新消息
                $pushMsg = $this->sendNewMsg($data);
                break;
            case 'change':
                $pushMsg = $this->changeRoom($data);
                break;
            case 'logout':
                $pushMsg = $this->doLogout($data);
                break;
            default:
                break;
        }
        $this->sendMsg($pushMsg, $data['fd']);
    }

    /**
     * 发送消息
     * @param $pushMsg 要发送的消息
     * @param $myfd 当前客户端的fd
     */
    public function sendMsg($pushMsg, int $myfd)
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
        $pushMsg['disfd'] = json_decode($this->getDistributeFd($myfd), true);
        $predis = PredisPool::defer();
        // pp("[发布消息] " . print_r($pushMsg, true));
        $obj = $predis->getRedis();
        $obj->publish($this->channel_name, json_encode($pushMsg));
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
     * 获取指定房间里的用户信息
     * @param $roomId
     * @return mixed
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

        $clientDomain = Config::getInstance()->getConf('CLIENT_DOMAIN');
        $pushMsg['data']['roomid'] = $data['roomid'];
        $pushMsg['data']['fd'] = $data['fd'];
        $pushMsg['data']['name'] = $data['params']['name'];
        $pushMsg['data']['avatar'] = $clientDomain . '/static/images/avatar/f1/f_' . rand(1, 12) . '.jpg';
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
        return $res;
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

    /**
     * 进入房间
     * @param $roomId
     * @param int $fd
     */
    public function joinRoom($roomId, int $fd)
    {
        $userId = $this->getUserId($fd);
        pp("[join room获取的userid] {$userId}");
        $disFd = $this->getDistributeFd($fd);

        $predis = PredisPool::defer();
        $predis->zAdd($this->rfMap, $roomId, $disFd);
        $predis->hSet("room:{$roomId}", $disFd, $userId);
    }

    /**
     * 根据fd获取用户id
     * @param int $fd
     */
    public function getUserId(int $fd)
    {
        $predis = PredisPool::defer();
        $disFd = $this->getDistributeFd($fd);
        $userId = $predis->hGet($this->fdToUserId, $disFd);
        return $userId;
    }

    public function cleanData()
    {
        try {
            $predis = PredisPool::defer();
            if ($predis->exists($this->rfMap)) {
                $predis->del($this->rfMap);
                pp("清理[{$this->rfMap}]成功");
            }
            if ($predis->exists($this->chatUser)) {
                $predis->del($this->chatUser);
                pp("清理[{$this->chatUser}]成功");
            }
            if ($predis->exists($this->fdToUserId)) {
                $predis->del($this->fdToUserId);
                pp("清理[{$this->fdToUserId}]成功");
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

    public function sendNewMsg(array $data)
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
            $tmp = $this->remind($data['roomid'], $pushMsg['data']['newmessage']);

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

    public function remind($roomid, $msg)
    {
        $data = array();
        if ($msg != "") {
            $data['msg'] = $msg;
            //正则匹配出所有@的人来
            $s = preg_match_all('~@(.+?)　~', $msg, $matches);
            if ($s) {
                $m1 = array_unique($matches[0]);
                $m2 = array_unique($matches[1]);

                $users = $this->getUsersByRoom($roomid);

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

    /**
     * 更换房间
     * @param $data
     * @return mixed
     */
    public function changeRoom($data)
    {
        $pushMsg['code'] = 6;
        $pushMsg['msg'] = '换房成功';

        $res = $this->changeUser($data['oldroomid'], $data['fd'], $data['roomid']);
        if ($res) {
            $pushMsg['data']['oldroomid'] = $data['oldroomid'];
            $pushMsg['data']['roomid'] = $data['roomid'];
            $pushMsg['data']['mine'] = 0;
            $pushMsg['data']['fd'] = $data['fd'];
            $pushMsg['data']['name'] = $data['params']['name'];
            $pushMsg['data']['avatar'] = $data['params']['avatar'];
            $pushMsg['data']['time'] = date("H:i", time());
            unset($data);
            return $pushMsg;
        }
    }

    /**
     * 切换房间需要更改的redis数据
     * @param $oldRoomId
     * @param $fd
     * @param $newRoomId
     * @return bool
     */
    public function changeUser($oldRoomId, $fd, $newRoomId)
    {
        //退出老房间
        $this->exitRoom($oldRoomId, $fd);
        //加入新房间
        $this->joinRoom($newRoomId, $fd);

        return true;
    }

    /**
     * 退出房间
     * @param $oldRoomId
     * @param $fd
     */
    public function exitRoom($oldRoomId, $fd)
    {
        $disFd = $this->getDistributeFd($fd);
        $predis = PredisPool::defer();
        $predis->hdel("room:{$oldRoomId}", $disFd);
        $predis->zRem($this->rfMap, $disFd);
    }

    /**
     * 通过客户端连接ID 获取用户的基本信息
     * @param $fd
     * @return mixed
     * @throws \EasySwoole\Component\Pool\Exception\PoolEmpty
     * @throws \EasySwoole\Component\Pool\Exception\PoolException
     */
    public function getUserInfoByFd($fd)
    {
        $userId = $this->getUserId($fd);
        $predis = PredisPool::defer();
        $userInfo = $predis->hGet($this->chatUser, $userId);
        return json_decode($userInfo, true);
    }

    public function getChanelName()
    {
        return $this->channel_name;
    }

    /**
     * 客户端关闭连接,退出登录
     */
    public function doLogout($data)
    {
        //退出登录,删除相关redis信息
        $this->logout($data['fd']);

        $pushMsg['code'] = 3;
        $pushMsg['msg'] = $data['params']['name'] . "退出了群聊";
        $pushMsg['data']['fd'] = $data['fd'];
        $pushMsg['data']['name'] = $data['params']['name'];
        unset($data);
        return $pushMsg;
    }

    /**
     * 退出登录时清除redis数据
     * @param $fd
     */
    public function logout($fd)
    {
        $predis = PredisPool::defer();
        $userId = $this->getUserId($fd);
        //关闭连接
        $this->close($fd);
        //从用户中删除
        $predis->hdel($this->chatUser, $userId);
    }

    public function close($fd)
    {
        $roomId = $this->getRoomIdByFd($fd);
        $this->exitRoom($roomId, $fd);
    }

    /**
     * 通过客户端连接ID 获取房间ID
     * @param $fd
     * @return mixed
     * @throws \EasySwoole\Component\Pool\Exception\PoolEmpty
     * @throws \EasySwoole\Component\Pool\Exception\PoolException
     */
    public function getRoomIdByFd($fd)
    {
        $disFd = $this->getDistributeFd($fd);
        $predis = PredisPool::defer();
        return $predis->zScore($this->rfMap,$disFd);
    }
}