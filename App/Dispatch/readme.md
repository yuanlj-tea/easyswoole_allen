#### 1、介绍

支持两种队列驱动：database、redis；

支持重试机制；

支持延时执行任务；

生产任务时，在协程mysql/redis连接池里生产，完全无阻塞；

引入DI container，反射执行任务逻辑；

消费任务是协程里消费，任务里有io阻塞时，队列消费完全非阻塞；

database驱动在任务失败时，会入failed_jobs表；

redis驱动在任务失败时，用channel做协程间通讯入失败队列，基于pop会阻塞检测任务是否失败导致会阻塞消费，暂时去掉了入失败队列；

#### 2、显示帮助提示：

```shell
php Job.php 或 php Job.php help
```

![](https://ws1.sinaimg.cn/large/006tNc79ly1g1stvjmbqrj30j704y3zw.jpg)

#### 3、database驱动需要生成数据表，命令：

```shell
php Job.php gen_database

会自动生成jobs、failed_jobs表，如果之前数据库里已经有这两张表，会被覆盖；
```

#### 4、支持两种消费方式：

- 基于DispatchProvider中配置的key消费，驱动、队列名不能自己配置，只能用任务类里配置的驱动、队列名；

  这种方式需要在DispatchProvider类的provider属性中配置key=>任务类的映射关系；

  ```shell
  php Job.php class=test_job
  
  注意:这种方式，只会使用任务类里配置的驱动、重试次数、队列名，生产任务时指定的参数无效。建议使用下面的方式
  ```

- 消费时指定驱动、队列名、重试次数

- ```shell
  php Job.php driver=redis/database(驱动名) queue=queue_name(队列名) tries=0(失败重试次数)
  ```

#### 5、说明：

任务类要继承Dispatcher抽象类，实现run方法，要执行的任务逻辑写在run方法里；

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

- 生产任务时可以设定延时消费、指定生产队列驱动、指定队列名：(优先级高于上面默认的)

  ```php
  $job = (new \App\Dispatch\TestJob(1,'foo',['foo','bar']))
    //延时5秒消费任务
    ->setDelay(5*1000)
    //设置队列驱动为database
    ->setQueueDriver('database')
    //设置队列名为test_queue_name
    ->setQueueName('test_queue_name');
  
  $job->dispatch($job);
  ```

  setDelay : 设置延时时间，单位毫秒；有延迟不支持重试；

  setQueueDriver : 设置队列驱动

  setQueueName : 设置队列名

#### 6、配置文件：

在config.php中设置redis/mysql连接配置：

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
    ]
];
```

