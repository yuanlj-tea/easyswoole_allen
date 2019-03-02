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
use EasySwoole\Http\AbstractInterface\Controller;

class Index extends Controller
{
    public function index()
    {
        $request = $this->request();
        $response = $this->response();

        $this->writeJson(200, 'hello world');
    }

    public function getCsrfToken()
    {
        $this->writeJson(200, $this->session()->get('csrf_token'));
    }

    public function onRequest(? string $action): ?bool
    {
        //全局要排除中间件的方法
        $this->middlewareExcept = [
            // Index::class.'\getCsrfToken',
        ];
        //要继承的中间件
        $this->middleware = [
            CorsMiddleware::class,
            ValidateCsrfToken::class,
        ];
        return parent::onRequest($action);
    }
}