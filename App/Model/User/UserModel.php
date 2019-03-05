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
    protected $table = 'test';


    public function getAll(int $page=1,int $pageSIze = 10)
    {
        $data = $this->db->withTotalCount()->orderBy('id','desc')->get($this->table,[($page-1)*$pageSIze,$page*$pageSIze]);
        $total = $this->db->getTotalCount();
        return ['data'=>$data,'total'=>$total];
    }
}