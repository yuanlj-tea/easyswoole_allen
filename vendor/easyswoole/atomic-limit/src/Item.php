<?php


namespace EasySwoole\AtomicLimit;


use EasySwoole\Component\AtomicManager;

class Item
{
    protected $max = 100;
    protected $itemName;
    protected $atomic;

    function __construct(string $itemName)
    {
        $this->itemName = $itemName;
        AtomicManager::getInstance()->add($itemName,0);
        $this->atomic = AtomicManager::getInstance()->get($itemName);
    }

    /**
     * @return int
     */
    public function getMax(): int
    {
        return $this->max;
    }

    /**
     * @param int $max
     */
    public function setMax(int $max): void
    {
        $this->max = $max;
        $this->atomic->set(0);
    }

    public function left():int
    {
        return $this->atomic->get();
    }

    public function isAllow():bool
    {
        if($this->atomic->add() <= $this->max){
            return true;
        }else{
            return false;
        }
    }

    public function restore(int $val = 0)
    {
        $this->atomic->set($val);
    }
}