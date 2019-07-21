<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/4
 * Time: 11:20 PM
 */

namespace App\HttpController\Base;


use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\Config;

class BaseMysql extends Base
{
    protected $db;

    public function onRequest(? string $action): ?bool
    {
        $conf = Config::getInstance();
        $timeout = $conf->getConf('MYSQL.POOL_TIME_OUT');
        $mysqlObject = PoolManager::getInstance()->getPool(MysqlPool::class)->getObj($timeout);

        if($mysqlObject instanceof MysqlObject){
            $this->db = $mysqlObject;
        }else{
            throw new \Exception('url :' . $this->request()->getUri()->getPath() . ' error,Mysql Pool is Empty');
        }

        return parent::onRequest($action);

    }

    protected function gc()
    {
        if($this->db instanceof MysqlObject){
            PoolManager::getInstance()->getPool(MysqlPool::class)->recycleObj($this->db);

            // 请注意 此处db是该链接对象的引用 即使操作了回收 仍然能访问
            // 安全起见 请一定记得设置为null 避免再次被该控制器使用导致不可预知的问题
            $this->db = null;
        }
    }

    protected function getDbConnection(): MysqlObject
    {
        return $this->db;
    }
}