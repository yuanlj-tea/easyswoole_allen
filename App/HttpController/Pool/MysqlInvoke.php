<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/3/5
 * Time: 11:48
 */

namespace App\HttpController\Pool;


use App\Model\User\UserBean;
use App\Model\User\UserModel;
use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Http\Message\Status;

class MysqlInvoke extends Controller
{
    public function index()
    {
        try{
            MysqlPool::invoke(function (MysqlObject $mysqlObj){
                $model = new UserModel($mysqlObj);
                $model->insert(new UserBean($this->request()->getRequestParam()));
            });
        }catch (\Throwable $throwable){
            $this->writeJson(Status::CODE_BAD_REQUEST, null, $throwable->getMessage());
        }
        $this->writeJson(Status::CODE_OK, null, 'success');
    }
}