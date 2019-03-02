<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/2
 * Time: 11:05 AM
 */

namespace App\Middleware;

use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Http\Session\SessionDriver;

abstract class MiddlewareAbstract
{
    /**
     * 错误信息
     * @var
     */
    protected $error = '';

    /**
     * 局部排除的方法
     * @var array
     */
    protected $except = [];

    /**
     * 中间件执行方法
     * @param Request $request 请求对象
     * @param Response $response 响应对象
     * @return bool
     */
    abstract function exec(Request $request, Response $response, SessionDriver $session): bool;

    /**
     * 获取错误信息
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * 返回局部排除的方法
     * @return array
     */
    public function getExcept()
    {
        return $this->except;
    }
}