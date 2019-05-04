<?php
/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2019/4/3
 * Time: 15:32
 */

return [
    'MYSQL' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'user' => 'root',
        'timeout' => '5',
        'charset' => 'utf8mb4',
        'password' => '123456',
        'database' => 'test',
        'POOL_MAX_NUM' => '20',
        'POOL_MIN_NUM' => '5',
        'POOL_TIME_OUT' => '0.5',
    ],
    //redis config
    'REDIS' => [
        'host' => '127.0.0.1',
        'port' => '6379',
        'auth' => '123456',
        'POOL_MAX_NUM' => '20',
        'POOL_MIN_NUM' => '5',
        'POOL_TIME_OUT' => '0.5',
    ],
    'AMQP' => [
        'host' => '192.168.1.207',
        'port' => 5672,
        'user' => 'guest',
        'pwd' => 'guest',
        'vhost' => '/',
        'POOL_MAX_NUM' => '20',
        'POOL_MIN_NUM' => '5',
        'POOL_TIME_OUT' => '0.5',
    ],

];