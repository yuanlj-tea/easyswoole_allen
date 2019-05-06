<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/3/2
 * Time: 12:10 AM
 */

namespace App\Middleware;

use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Http\Session\SessionDriver;
use App\HttpController\Index;

class CorsMiddleware extends MiddlewareAbstract
{
    /**
     * CORS中间件
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function exec(Request $request, Response $response,SessionDriver $session): bool
    {
        $response->withHeader('Access-Control-Allow-Origin', '*');
        $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->withHeader('Access-Control-Allow-Credentials', 'true');
        $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        if ($request->getMethod() === 'OPTIONS') {
            $response->withStatus(Status::CODE_OK);
            $response->end();
        }

        return true;
    }

}