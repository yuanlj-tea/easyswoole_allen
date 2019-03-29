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
use App\Utility\Pool\RedisObject;
use App\Utility\Pool\RedisPool;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\FastCache\Cache;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Utility\SnowFlake;
use EasySwoole\Utility\Str;
use Swlib\Saber;
use Swlib\SaberGM;

class Index extends Controller
{
    public function index()
    {
        RedisPool::invoke(function (RedisObject $redis){
            $key = 'user:1:api_count';
            $limit = 1;

            $check = $redis->exists($key);
            if($check){
                $redis->incr($key);
                $count = $redis->get($key);
                if($count>$limit){
                    pp('访问太过频繁');
                    return;
                }
            }else{
                $redis->incr($key);
                $redis->expire($key,2);
            }

            $count = $redis->get($key);
            pp("访问成功,count：{$count}");
        });


        /*$request = $this->request();
        $response = $this->response();

        TaskManager::async(function (){
            pp("异步任务");
        });

        $this->writeJson(200, 'hello world');*/
    }

    /**
     * 获取csrf_token
     */
    public function getCsrfToken()
    {
        $this->writeJson(200, $this->session()->get('csrf_token'));
    }

    public function testSaber()
    {



        [$html] = SaberGM::list([
            'uri' => [
                'http://www.blog.com/sso/server/login'
            ]
        ]);
        var_dump($html->getParsedDomObject()->getElementsByTagName('button')->item(0)->getAttribute('id'));
        // var_dump($html->getParsedHtml()->getElementsByTagName('input')->item(0)->textContent);
        $this->response()->write('ok');
        return;
        [$json, $xml, $html] = SaberGM::list([
            'uri' => [
                'http://httpbin.org/get',
                'http://www.w3school.com.cn/example/xmle/note.xml',
                'http://httpbin.org/html'
            ]
        ]);
        var_dump($json->getParsedJson());
        var_dump($json->getParsedJsonObject());
        var_dump($xml->getParsedXml());
        var_dump($html->getParsedHtml()->getElementsByTagName('h1')->item(0)->textContent);
        //测试自动登录
        $session = Saber::session([
            'base_uri' => 'http://www.blog.com',
            'redirect' => 0
        ]);
        $session->post('/sso/server/test');
        $res = $session->get('/sso/server/test');
        echo $res->body;
        $this->response()->write($res->body);

        /*$session = Saber::session([
            'base_uri' => 'http://www.blog.com',
            'redirect' => 0
        ]);
        $redirect_url = base64_encode('hehe');
        $res=$session->post('/sso/server/login?redirect_url='.$redirect_url,['user'=>'demo','pwd'=>'demo']);

        echo $res->body;

        $getRes = $session->get('/sso/server/login')->body;
        echo $getRes;*/

    }

    public function upload()
    {
        $request = $this->request();
        $file = $request->getUploadedFile('img')->moveTo('/tmp/test.jpg');
        p($file);
    }

    public function onRequest(? string $action): ?bool
    {
        //全局要排除中间件的方法
        $this->middlewareExcept = [
            Index::class.'\getCsrfToken',
        ];
        //要继承的中间件
        $this->middleware = [
            CorsMiddleware::class,
            ValidateCsrfToken::class,
        ];
        return parent::onRequest($action);
    }
}