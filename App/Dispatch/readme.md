#### 1、介绍

支持自定义命令：php Job.php command=foo:bar;

支持队列驱动：database、redis、rabbitmq、nsq、kafka;

#### 2、显示帮助提示

```shell
php Job.php 或 php Job.php help
```

![](https://ws1.sinaimg.cn/large/006tNc79ly1g1stvjmbqrj30j704y3zw.jpg)

#### 3、支持自定义command

```shell
在/App/Dispatch/Console目录下创建console类继承AbstractConsole类，并在handle方法里实现业务逻辑
```

#### 4、生产消费

```
生产者类都在App/Dispatch/DispatchHandler目录下
```

##### 4.1、mysql驱动

```
需要先生成队列表：
	php Job.php command=gen:queue:database
	会自动生成jobs、failed_jobs表，如果之前数据库里已经有这两张表，会被覆盖；
生产：
	new MysqlDispatch(new TestJob(1,'foo',['bar']),'queue');
消费：
	cd App/Dispatch && php Job.php driver=database queue=queue tries=0
```

##### 4.2、redis驱动

```
生产：
	new RedisDispatch(new TestJob(1, 'foo', ['bar']), 'queue');
消费：
	cd App/Dispatch && php Job.php driver=redis queue=queue tries=0
```

##### 4.3、rabbitmq驱动

```
生产：
  $exchangeName = 'direct_logs';
  $queueName = 'queue';
  $routeKey = 'test';
  $type = 'direct';

  new AmqpDispatch(new TestJob(1, 'bar', ['foo']), $type, $exchangeName, $queueName, $routeKey);
消费：
	php Job.php driver=amqp type=direct exchange=direct_logs queue=queue route_key=test tries=0
```

##### 4.3、nsq驱动

```
生产：
	$topic = 'test2';
	new NsqDispatch(new TestJob(1,'foo',['bar']),$topic);
消费：
	php Job.php driver=nsq topic=test2 channel=my_channel tries=0
```

##### 4.4、kafka驱动

```php
生产：
	new KafkaDispatch(new TestJob(1, 'foo', ['bar']), 'test', '');
消费：
	php Job.php driver=kafka topic=test groupId=test
```



#### 5、说明

任务类要继承\App\Dispatch\Dispatcher抽象类，实现run方法，要执行的任务逻辑写在run方法里；

- 默认重试次数:3次

  任务类里可重写重试次数：

  ```php
  protected $tries = 5;
  ```

- 默认队列驱动:redis

  任务类里可重写队列驱动：

  ```php
  protected static $queueDriver = 'database';
  ```

- 默认队列名：default_queue_name

  任务类里可重写队列名：

  ```php
  protected static $queueName = 'test_queue_name';
  ```


#### 6、配置文件

在dev.php中设置redis、mysql、amqp、nsq连接配置：

```php
return [
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
        'host' => '192.168.79.206',
        'port' => 5672,
        'user' => 'guest',
        'pwd' => 'guest',
        'vhost' => '/',
        'POOL_MAX_NUM' => '20',
        'POOL_MIN_NUM' => '5',
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
```

