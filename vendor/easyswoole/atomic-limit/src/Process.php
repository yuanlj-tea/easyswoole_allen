<?php


namespace EasySwoole\AtomicLimit;


use EasySwoole\Component\Process\AbstractProcess;

class Process extends AbstractProcess
{
    protected function run($arg)
    {
        /** @var AtomicLimit $manager */
        $manager = $this->getArg()['manager'];
        $tick = $this->getArg()['tick'];
        $this->addTick($tick,function ()use($manager){
            $manager->restore();
        });
    }
}