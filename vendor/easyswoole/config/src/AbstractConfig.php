<?php


namespace EasySwoole\Config;


abstract class AbstractConfig
{
    private $isDev;

    function __construct(bool $isDev = true)
    {
        $this->isDev = $isDev;
    }

    protected function isDev():bool
    {
        return $this->isDev;
    }

    abstract function getConf($key = null);
    abstract function setConf($key,$val):bool ;
    abstract function load(array $array):bool ;
    abstract function merge(array $array):bool ;
    abstract function clear():bool ;
}