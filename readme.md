#### 用easyswoole actor实现的聊天室

##### 1、配置前端目录的虚拟主机

```
配置虚拟主机，目录指向/path/easyswoole_allen/client
```

[前端虚拟主机配置文件](<https://github.com/a1554610616/easyswoole_allen/blob/actor_chat/App/WebSocket/webim_cluster_client.conf>)


##### 2、修改配置文件

```
1、修改produce.php中的配置CLIENT_DOMAIN配置为如上1中的虚拟主机域名；
2、将/path/client/static/js/init.js中的wsserver修改为如上1中的虚拟主机域名；
```

##### 3、start

```
执行：
php easyswoole start produce
```

##### 4、访问

> 访问如上2中的虚拟主机域名，如192.168.79.206:8081/index.html

#### 5、流程图

![](<https://www.processon.com/view/link/5d0c566ae4b0d4ba353f0eca>)