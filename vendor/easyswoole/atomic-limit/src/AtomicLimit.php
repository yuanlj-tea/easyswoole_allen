<?php


namespace EasySwoole\AtomicLimit;


use EasySwoole\Component\Process\Config;
use EasySwoole\Component\Singleton;

class AtomicLimit
{
    use Singleton;

    private $list = [];

    function addItem(string $item):Item
    {
        if(!isset($this->list[$item])){
            $this->list[$item] = new Item($item);
        }
        return $this->list[$item];
    }

    public static function left(string $item):?int
    {
        $item = static::getInstance()->item($item);
        if($item){
            return $item->left();
        }else{
            return null;
        }
    }

    public static function isAllow(string $item):bool
    {
        $item = static::getInstance()->item($item);
        if($item){
            return $item->isAllow();
        }else{
            return false;
        }
    }

    public function restore(?string $item = null)
    {
        if($item){
            $item = static::getInstance()->item($item);
            if($item){
                $item->restore();
            }
        }else{
            foreach ($this->list as $item){
                $item->restore();
            }
        }
    }

    public function item(string $item):?Item
    {
        if(isset($this->list[$item])){
            return $this->list[$item];
        }else{
            return null;
        }
    }

    public function enableProcessAutoRestore(\swoole_server $server,int $tick = 5*1000)
    {
        $config = new Config();
        $config->setArg([
            'tick'=>$tick,
            'manager'=>$this
        ]);
        $config->setProcessName('AtomicLimit.AutoRestoreProcess');
        $p = new Process($config);
        $server->addProcess($p->getProcess());
    }
}