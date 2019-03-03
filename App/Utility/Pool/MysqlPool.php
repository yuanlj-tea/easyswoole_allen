<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/2
 * Time: 8:23 PM
 */

namespace App\Utility\Pool;

use EasySwoole\EasySwoole\Config;
use EasySwoole\Component\Pool\AbstractPool;
use EasySwoole\Mysqli\Config as MysqliConfig;

class MysqlPool extends AbstractPool
{
    protected function createObject()
    {
        $conf = Config::getInstance()->getConf('MYSQL');
        $dbConf = new MysqliConfig($conf);
        return new MysqlObject($dbConf);
    }
}