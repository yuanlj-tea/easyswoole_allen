<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/3/5
 * Time: 18:18
 */

namespace App\Model;

use App\Utility\Pool\MysqlObject;

class BaseModel
{
    public $db;

    public function __construct(MysqlObject $db)
    {
        $this->db = $db;
    }

    public function getDbConnection(): MysqlObject
    {
        return $this->db;
    }

}