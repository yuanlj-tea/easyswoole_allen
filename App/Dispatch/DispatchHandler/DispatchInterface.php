<?php
/**
 * Created by PhpStorm.
 * User: allen
 * Date: 2019/5/5
 * Time: 11:19 PM
 */

namespace App\Dispatch\DispatchHandler;

use App\Dispatch\Dispatcher;

interface DispatchInterface
{
    public function dispatch(Dispatcher $dispatcher);
}