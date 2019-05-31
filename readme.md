#### 基于easyswoole框架实现的服务端可配置多台服务器的聊天室

##### 1、配置前端目录的虚拟主机

```
配置虚拟主机，目录指向/path/client
```

[前端虚拟主机配置文件](<https://github.com/a1554610616/easyswoole_allen/blob/master/App/WebSocket/webim_cluster_client.conf>)

##### 2、websocket负载均衡配置

[nginx配置](<https://github.com/a1554610616/easyswoole_allen/blob/master/App/WebSocket/webim_cluster_proxy.conf>)

##### 3、修改配置文件

```
1、修改produce.php中的配置CLIENT_DOMAIN配置为如上1中的虚拟主机域名；
2、将/path/client/static/js/init.js中的wsserver修改为如上2中的负载均衡的nginx端口
```

##### 4、start

```
执行：
php easyswoole start produce
```

##### 5、访问

> 访问如上1中的前端虚拟主机，如192.168.1.207:8081/index.html