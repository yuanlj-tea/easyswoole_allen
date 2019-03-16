<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/3/5
 * Time: 18:07
 */

namespace App\HttpController\Pool;


use App\HttpController\Base\BaseMysql;
use App\Model\User\UserBean;
use App\Model\User\UserModel;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Http\Message\Status;
use EasySwoole\Utility\SnowFlake;

class Mysql extends BaseMysql
{
    public function index()
    {
        $server=ServerManager::getInstance()->getSwooleServer();
        $workerId=$server->worker_id;

        $db=$this->getDbConnection();
        $str=SnowFlake::make(rand(0,31),$workerId);

        $db->insert('t',['str'=>$str]);
        // $db = $this->getDbConnection();
        // $data = $db->where('id',1,'=')->get('test');
        // $this->writeJson(1,$data);
    }

    public function getAll()
    {
        $user = new UserModel($this->getDbConnection());
        $data = $user->getAll(1,4);
        $this->writeJson(1,$data,'succ');
    }

    public function getOne()
    {
        $param = $this->request()->getRequestParam();
        if(isset($param['id'])){
            $bean = new UserBean($param);
            $model = new UserModel($this->getDbConnection());
            $result = $model->getOne($bean);

            if($result){
                $this->writeJson(1,$result,'succ');
            }else{
                $this->writeJson(1,$result,'用户不存在');
            }


        }else{
            $this->writeJson(0, null, 'id不能为空');
        }
    }

    public function insert()
    {
        $param = $this->request()->getRequestParam();
        if(!isset($param['name']) || !isset($param['age'])){
            $this->writeJson(0,null,'缺少参数');
            $this->response()->end();
        }
        $model = new UserModel($this->db);
        $res = $model->insert(new UserBean($param));
        if($res){
            $this->writeJson(1,null,'添加成功');
        }else{
            $this->writeJson(0,null,'添加失败');
        }
    }
}