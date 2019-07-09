<?php


namespace EasySwoole\Session;


use EasySwoole\Http\AbstractInterface\Controller;

abstract class AbstractSessionController extends Controller
{
    private $session;

    protected abstract function sessionHandler():\SessionHandlerInterface;

    protected function session(): SessionDriver
    {
        if($this->session == null){
            $this->session = new SessionDriver($this->sessionHandler(),$this->request(),$this->response());
        }
        return $this->session;
    }

    protected function gc()
    {
        parent::gc();
        if($this->session){
            $this->session()->close();
            $this->session = null;
        }
    }
}