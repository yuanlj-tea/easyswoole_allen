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
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Http\Message\Status;
use EasySwoole\Utility\SnowFlake;

class Mysql extends BaseMysql
{
    public function index()
    {
        $status = PoolManager::getInstance()->getPool(MysqlPool::class)->status();
        pp($status);
        /*$server=ServerManager::getInstance()->getSwooleServer();
        $workerId=$server->worker_id;

        $db=$this->getDbConnection();
        $str=SnowFlake::make(rand(0,31),$workerId);

        $db->insert('t',['str'=>$str]);



        $db = $this->getDbConnection();
        $str = $this->getUid();
        $db->insert('t',['str'=>$str]);*/

        // $str = gen_uid();
        // $db->insert('t',['str'=>$str]);

        // $data = $db->where('id',1,'=')->get('test');
        // $this->writeJson(1,$data);
    }

    public function getUid()
    {
        while(true){
            //订购日期
            $order_date = date('Y-m-d');
            //订单号码主体（YYYYMMDDHHIISSNNNNNNNN）
            $order_id_main = date('YmdHis') . rand(10000000,99999999);
            //订单号码主体长度
            $order_id_len = strlen($order_id_main);
            $order_id_sum = 0;
            for($i=0; $i<$order_id_len; $i++){
                $order_id_sum += (int)(substr($order_id_main,$i,1));
            }
            //唯一订单号码（YYYYMMDDHHIISSNNNNNNNNCC）
            $order_id = $order_id_main . str_pad((100 - $order_id_sum % 100) % 100,2,'0',STR_PAD_LEFT);

            $db = $this->getDbConnection();
            $data = $db->where('str',$order_id,'=')->getOne('t','*');
            if(empty($data)){
                return $order_id;
                break;
            }
        }
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
        $status = PoolManager::getInstance()->getPool(MysqlPool::class)->status();
        pp($status);
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

    public function testInsert()
    {
        ignore_user_abort();
        $s = microtime(true);

        $filePath = EASYSWOOLE_ROOT.'/500W.csv';
        $fp = fopen($filePath,'r');

        $count=0;
        $arr=$tmp=[];
        while (!feof($fp)){
            $str = trim(fgets($fp));
            if(!empty($str)){
                $tmp = explode(',',$str);
                array_push($arr,$tmp);
                $count++;

                if($count==8000){
                    $this->insertBatch('t1',['tel','tel1'],$arr,4000);
                    $count=0;
                    $arr=$tmp=[];
                }
            }
        }
        $this->insertBatch('t1',['tel','tel1'],$arr,4000);

        fclose($fp);
        $e = microtime(true);
        pp("耗时".($e-$s)."秒");
    }

    /**
     * 批量插入数据
     * @param $table 表名
     * @param $column 字段名 eg:['id','name']
     * @param $data 要插入的数据 eg:[[1,'zs'],[2,'ls']]
     * @param int $splitNum 分段数，默认100
     */
    public function insertBatch($table,$column,$data,$splitNum=100)
    {
        $db=$this->getDbConnection();
        foreach (array_chunk($data,$splitNum) as $values){
            $sqlBatch = insert_batch($table,$column,$values);
            $db->rawQuery($sqlBatch);
        }
    }
}