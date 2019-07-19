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
            'reload_async' => true,
            'package_max_length' => 100 * 1024 * 1024
        ],
    ],
    'TEMP_DIR' => null,
    'LOG_DIR' => null,
    'CONSOLE'     => [//console组件配置,完整配置可查看:http://easyswoole.com/Manual/3.x/Cn/_book/SystemComponent/Console/Introduction.html
        'ENABLE'         => true,//是否开启console
        'LISTEN_ADDRESS' => '127.0.0.1',//console服务端监听地址
        'HOST'           => '127.0.0.1',//console客户端连接远程地址
        'PORT'           => 9500,//console服务端监听端口,客户端连接远程端口
        'EXPIRE'         => '120',//心跳超时时间
        // 'AUTH'           => null,//鉴权密码,如不需要鉴权可设置null
        'AUTH'           => [
            [
                'USER'        => 'root',
                'PASSWORD'    => 'root',
                'MODULES'     => [
                    'auth', 'server', 'help', 'test'
                ],
                'PUSH_LOG'    => true
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
        //连接池配置需要根据注册时返回的poolconfig进行配置,只在这里配置无效
        'intervalCheckTime'    => 30 * 1000,//定时验证对象是否可用以及保持最小连接的间隔时间
        'maxIdleTime'          => 15,//最大存活时间,超出则会每$intervalCheckTime/1000秒被释放
        'maxObjectNum'         => 20,//最大创建数量
        'minObjectNum'         => 5,//最小创建数量 最小创建数量不能大于等于最大创建
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
    ],
    'UPLOAD_FILE_PATH' => '/data/easyswoole-upload'
];
