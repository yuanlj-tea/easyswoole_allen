<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/4
 * Time: 11:18 PM
 */

namespace App\HttpController\Base;

use EasySwoole\Http\AbstractInterface\Controller;

abstract class Base extends Controller
{
    public function index()
    {
        $this->actionNotFound('index');
    }
}