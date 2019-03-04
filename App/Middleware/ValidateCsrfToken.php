<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/1
 * Time: 11:35 PM
 */

namespace App\Middleware;

use App\HttpController\Index;
use EasySwoole\FastCache\Cache;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Http\Session\SessionDriver;
use EasySwoole\Utility\SnowFlake;

class ValidateCsrfToken extends MiddlewareAbstract
{
    protected $except = [
        Index::class . '\getCsrfToken',
    ];

    /**
     * 验证csrf_token中间件
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function exec(Request $request, Response $response, SessionDriver $session): bool
    {
        $response->withHeader("Content-Type", "application/json; charset=utf-8");

        $csrf_token = $session->get('csrf_token');
        $user_token = $request->getRequestParam('token');

        //只有POST请求方式才验证csrf_token
        if ($request->getMethod() == 'POST' && $user_token != $csrf_token) {
            $this->error = 'csrf_token验证失败';
            return false;
        }

        return true;
    }


}