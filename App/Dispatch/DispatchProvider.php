<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/4/3
 * Time: 14:22
 */

namespace App\Dispatch;

class DispatchProvider
{
    private $provider = [
        'test_job' => TestJob::class,
    ];

    /**
     * 获取允许的队列
     * @return array
     */
    public function getProvider()
    {
        return $this->provider;
    }
}