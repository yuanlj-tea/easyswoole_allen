<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/3/5
 * Time: 18:57
 */

namespace App\Model\User;

use App\Model\BaseModel;

class UserModel extends BaseModel
{
    protected $table = 'user';


    public function getAll(int $page=1,int $pageSIze = 10)
    {
        $data = $this->db->withTotalCount()->orderBy('id','desc')->get($this->table,[($page-1)*$pageSIze,$page*$pageSIze]);
        $total = $this->db->getTotalCount();
        return ['data'=>$data,'total'=>$total];
    }

    public function getOne(UserBean $bean)
    {
        $data = $this->db->where('id',$bean->getId())->getOne($this->table);
        return empty($data) ? null : new UserBean($data);
    }

    public function insert(UserBean $bean)
    {
        return $this->db->insert($this->table,$bean->toArray(null,UserBean::FILTER_NOT_NULL));
    }
}