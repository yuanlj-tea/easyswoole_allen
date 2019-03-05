<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/3/5
 * Time: 18:07
 */

namespace App\HttpController\Pool;


use App\HttpController\Base\BaseMysql;
use App\Model\User\UserModel;

class Mysql extends BaseMysql
{
    public function index()
    {
        $db = $this->getDbConnection();
        $data = $db->where('id',1,'=')->get('test');
        $this->writeJson(200,$data);
    }

    public function getAll()
    {
        $user = new UserModel($this->getDbConnection());
        $data = $user->getAll(2,2);
        $this->writeJson(200,$data,'succ');
    }
}