<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/1/26
 * Time: 11:52 PM
 */

namespace App\HttpController;

use App\Middleware\CorsMiddleware;
use App\Middleware\ValidateCsrfToken;
use EasySwoole\FastCache\Cache;
use EasySwoole\EasySwoole\Logger;

class Index extends AbstractController
{
    public function index()
    {
        
    }

    public function onRequest(? string $action): ?bool
    {
        //局部要排除中间件的方法
        $this->middlewareExcept = [
            //Index::class . '\getCsrfToken',
        ];
        //要继承的中间件
        $this->middleware = [
            CorsMiddleware::class
        ];
        return parent::onRequest($action);
    }
}