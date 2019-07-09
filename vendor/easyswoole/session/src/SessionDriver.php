<?php


namespace EasySwoole\Session;


use EasySwoole\Http\Request;
use EasySwoole\Http\Response;

class SessionDriver
{
    private $handler = null;
    private $sessionName = 'ESSession';
    private $sessionId;
    private $isStart = false;
    private $savePath;
    private $sessionData;
    private $request;
    private $response;
    private $gcMaxLifetime = 1440;
    private $gcProbability = 1;

    function __construct(\SessionHandlerInterface $sessionHandler,Request $request,Response $response)
    {
        $this->handler = $sessionHandler;
        $this->request = $request;
        $this->response = $response;
        $this->savePath = sys_get_temp_dir().'/Session';
    }


    function gcMaxLifetime(int $maxlifetime = null)
    {
        if($maxlifetime != null){
            $this->gcMaxLifetime = $maxlifetime;
        }
        return $this->gcMaxLifetime;
    }

    function gcProbability(int $gcProbability = null)
    {
        if($gcProbability !== null){
            $this->gcProbability = $gcProbability;
        }
        return $this->gcProbability;
    }

    /*
     * 返回当前保存路径或者是更改
     */
    function savePath(string $savePath = null)
    {
        if(empty($savePath)){
            return $this->savePath;
        }
        if(!$this->isStart){
            $this->savePath = $savePath;
            return $this->savePath;
        }else{
            return false;
        }
    }

    function sessionId(string $sessionId = null)
    {
        if(empty($sessionId)){
            return $this->sessionId;
        }
        if(!$this->isStart){
            $this->sessionId = $sessionId;
            return $this->sessionId;
        }else{
            return false;
        }
    }

    function start(string $sessionId = null)
    {
        if($this->isStart){
            return true;
        }
        if(!empty($sessionId)){
            $this->sessionId = $sessionId;
        }
        $this->isStart = $this->handler->open($this->savePath,$this->sessionName);
        /*
         *cookie检测
         */
        $cookie = $this->request->getCookieParams($this->sessionName);
        if(empty($cookie)){
            $this->sessionId = md5(microtime(true) . $this->request->getSwooleRequest()->fd);
        }else{
            $this->sessionId = $cookie;
        }
        if($cookie != $this->sessionId){
            $this->request->withCookieParams([
                    $this->sessionName => $this->sessionId
                ]
                +
                $this->request->getCookieParams()
            );
            $this->response->setCookie($this->sessionName, $this->sessionId);
        }
        /*
         * 数据载入
         */
        $data = $this->handler->read($this->sessionId);
        if(!empty($data)){
            $this->sessionData = unserialize($data);
            if(!is_array($this->sessionData)){
                $this->sessionData = [];
            }
        }else{
            $this->sessionData = [];
        }
        mt_srand();
        $i = rand(0,100);
        if($i < $this->gcProbability){
            $this->handler->gc($this->gcMaxLifetime);
        }
        return $this->isStart;
    }

    function sessionName(string $sessionName = null)
    {
        if(empty($this->sessionName)){
            return $this->sessionName;
        }
        if(!$this->isStart){
            $this->sessionName = $sessionName;
            return $this->sessionName;
        }else{
            return false;
        }
    }

    function set($key,$val)
    {
        if($this->isStart){
            $this->sessionData[$key] = $val;
            return true;
        }else{
            return false;
        }
    }

    function get($key)
    {
        if($this->isStart){
            if(isset($this->sessionData[$key])){
                return $this->sessionData[$key];
            }else{
                return null;
            }
        }else{
            return null;
        }
    }

    function unset($key)
    {
        if($this->isStart){
            if(isset($this->sessionData[$key])){
                unset( $this->sessionData[$key]);
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    function destroy()
    {
        if($this->isStart){
            $this->sessionData = [];
            return $this->handler->destroy($this->sessionId);
        }
        return false;
    }

    function close()
    {
        if($this->isStart){
            $this->handler->write($this->sessionId,serialize($this->sessionData));
            $this->sessionName = 'ESSession';
            $this->isStart = false;
            $this->savePath = '/';
            $this->sessionData = null;
            $this->sessionId = null;
            $this->isStart = false;
            return $this->handler->close();
        }
        return false;
    }

    function gc(int $maxLifeTime)
    {
        return $this->handler->gc($maxLifeTime);
    }
}