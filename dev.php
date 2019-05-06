<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-01
 * Time: 20:06
 */

return [
    'SERVER_NAME' => "EasySwoole",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9501,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 8,
            'max_request' => 5000,
            'task_worker_num' => 8,
            'task_max_request' => 1000,
            'task_enable_coroutine' => true,
            'reload_async' => true
        ],
    ],
    'TEMP_DIR' => null,
    'LOG_DIR' => null,
    'CONSOLE' => [
        'ENABLE' => true,
        'LISTEN_ADDRESS' => '127.0.0.1',
        'HOST' => '127.0.0.1',
        'PORT' => 9500,
        'EXPIRE' => '120',
        'PUSH_LOG' => true,
        'AUTH' => [
            [
                'USER'=>'root',
                'PASSWORD'=>'123456',
                'MODULES'=>[
                    'auth','server','help'
                ],
                'PUSH_LOG' => true,
            ]
        ]
    ],
    'FAST_CACHE' => [
        'PROCESS_NUM' => 0,
        'BACKLOG' => 256,
    ],
    'DISPLAY_ERROR' => true,

    //mysql config
    'MYSQL' => [
        'host'          => '127.0.0.1',
        'port'          => '3306',
        'user'          => 'root',
        'timeout'       => '5',
        'charset'       => 'utf8mb4',
        'password'      => '123456',
        'database'      => 'test',
        'POOL_MAX_NUM'  => '20',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '0.5',
    ],
    //redis config
    'REDIS' => [
        'host'          => '127.0.0.1',
        'port'          => '6379',
        'auth'          => '123456',
        'POOL_MAX_NUM'  => '20',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '0.5',
    ],
    'AMQP' => [
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'pwd' => 'guest',
        'vhost' => '/',
        'POOL_MAX_NUM'  => '20',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '0.5',
    ],
    'NSQ' => [
        'nsqd' => [
            "127.0.0.1:4150",
            "127.0.0.1:4151",
        ],
        'nsqlookupd' => '127.0.0.1:4161'
    ]
];
