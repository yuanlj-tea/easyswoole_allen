<?php

namespace App\Utility\Pool\Predis;

use App\Libs\Predis;
use EasySwoole\Component\Pool\PoolObjectInterface;

class PredisObject extends Predis implements PoolObjectInterface
{
    function objectRestore()
    {

    }

    function beforeUse(): bool
    {
        return true;
    }

    function gc()
    {
        $this->close();
    }

}