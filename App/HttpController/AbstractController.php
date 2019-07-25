<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/5/6
 * Time: 14:49
 */

namespace App\HttpController;

use App\Container\Container;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Utility\SnowFlake;

abstract class AbstractController extends Controller
{
    /**
     * 继承的中间件
     * @var array
     */
    protected $middleware = []; //继承的中间件

    /**
     * 容器对象
     * @var
     */
    private $container;

    /**
     * 局部要排除中间件的方法
     * @var array
     */
    protected $middlewareExcept = [];

    public function __construct()
    {
        parent::__construct();
        //获取容器单例
        $this->container = Container::getInstance();
    }

    protected function onRequest(?string $action): ?bool
    {
        //调用中间件,局部要排除中间件的方法不验证
        if (!empty($this->middleware) && !in_array(Static::class . '\\' . $action, $this->middlewareExcept)) {
            foreach ($this->middleware as $pipe) {
                $ins = $this->container->get($pipe);
                //中间件全局排除的方法,不验证
                if (in_array(Static::class . '\\' . $action, $ins->getExcept())) {
                    continue;
                }
                //执行中间件逻辑
                if (!$ins->exec($this->request(), $this->response())) {
                    $this->writeJson(200, '中间件验证失败', sprintf("中间件%s:验证失败,原因：%s", $pipe, $ins->getError()));
                    return false;
                }
            }
        }
        return true;
    }
}