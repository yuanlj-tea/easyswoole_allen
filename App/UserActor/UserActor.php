<?php

namespace App\UserActor;

use App\Libs\Command;
use App\RoomActor\RoomManager;
use App\Utility\Pool\Predis\PredisPool;
use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;
use EasySwoole\EasySwoole\ServerManager;

class UserActor extends AbstractActor
{
    /**
     * 客户端连接fd
     * @var
     */
    private $fd;

    /**
     * 用户email
     * @var
     */
    private $name;

    private $roomId;

    private $state;

    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('user_actor');
    }

    protected function onStart()
    {
        $this->name = $this->getArg()['name'];
        $this->fd = $this->getArg()['fd'];
        $this->roomId = $this->getArg()['roomId'];
        $this->state = $this->getArg()['state'];
        UserManager::addUser(new UserBean([
            'name' => $this->name,
            'actorId' => $this->actorId(),
            'state' => $this->state
        ]));

        pp(sprintf("[UserActor创建成功][name]%s[actorId]%s", $this->name, $this->actorId()));
    }

    protected function onMessage($msg)
    {
        $wsServer = ServerManager::getInstance()->getSwooleServer();
        if ($msg instanceof Command) {
            $arg = $msg->getArg();
            switch ($msg->getCommand()) {
                case $msg::RECONNECT:
                    $this->fd = $msg->getArg()['fd'];
                    $this->roomId = $msg->getArg()['roomId'];
                    $this->state = $msg->getArg()['state'];

                    $update = [
                        'state' => $this->state
                    ];
                    UserManager::updateUserInfo($this->name, $update);
                    pp(sprintf("[actor id]%s重新登录成功[fd]%d[roomId]%d", $this->actorId(), $this->fd, $this->roomId));
                    break;
                case $msg::LOGOUT:
                    if ($this->fd == $arg['fd']) {
                        //用户退出房间
                        UserManager::exitRoom($this->roomId, $this->name);

                        //给指定房间里的用户发消息,通知用户退出
                        $pushMsg['code'] = 3;
                        $pushMsg['msg'] = $this->name . "退出了群聊";
                        $pushMsg['data']['fd'] = $arg['fd'];
                        $pushMsg['data']['name'] = $this->name;
                        $pushMsg['data']['roomid'] = $this->roomId;
                        RoomManager::doLogout($this->roomId, $pushMsg);

                        $this->fd = 0;
                        $this->roomId = 0;
                        $this->state = 0;
                        $update = [
                            'state' => $this->state
                        ];
                        UserManager::updateUserInfo($this->name, $update);
                    }
                    break;
                case $msg::ROOM_SEND_LOGOUT:
                    $arg['data']['this_room'] = 0;
                    if ($this->roomId == $arg['data']['roomid']) {
                        $arg['data']['this_room'] = 1;
                    }

                    $info = $wsServer->getClientInfo($this->fd);
                    if($info && $info['websocket_status'] === WEBSOCKET_STATUS_FRAME){
                        $wsServer->push($this->fd, json_encode($arg));
                    }
                    break;
                case $msg::CHANGE_ROOM:
                    if ($this->name == $arg['data']['name']) {
                        $this->roomId = $arg['data']['roomid'];
                    }
                    break;
                case $msg::ALREADY_ONLINE:
                    $arg['data']['mine'] = 1;
                    $wsServer->push($arg['data']['fd'], json_encode($arg));
                    break;
                default:
                    break;
            }
        } else {
            if ($this->fd == $msg['data']['fd']) {
                $msg['data']['mine'] = 1;
            } else {
                $msg['data']['mine'] = 0;
            }
            $wsServer->push($this->fd, json_encode($msg));
        }
    }

    protected function onExit($arg)
    {

    }

    protected function onException(\Throwable $throwable)
    {

    }


}