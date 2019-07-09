<?php


namespace EasySwoole\Session\Test;

/*
 * 本处理器为测试用
 */
class RedisHandler implements \SessionHandlerInterface
{

    private $redis;
    private $savePath;
    private $name;

    function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function close()
    {
        $this->redis->close();
    }

    public function destroy($session_id)
    {
        return $this->redis->hdel($this->name,$session_id);
    }

    public function gc($maxlifetime)
    {
        /*
         * 这边空逻辑，请自行维护
         */
    }

    public function open($save_path, $name)
    {
        $this->savePath = $save_path;
        $this->name = $name;
        return true;
    }

    public function read($session_id)
    {
        return $this->redis->hget($this->name,$session_id);
    }

    public function write($session_id, $session_data)
    {
        return $this->redis->hset($this->name,$session_id,$session_data);
    }
}