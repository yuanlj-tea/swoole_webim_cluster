##### 基于swoole实现的聊天室

> 后台可以部署多台websocket服务器，通过nginx反向代理，配置负载均衡

1、先将client目录放置在您的web服务器下，打开client/static/js/init.js 文件，配置domain(client目录所配置的域名)、wsserver(多机：nginx负载均衡的ip，单机：单机的websocket ip);

2、修改server/App/Config/config.php里的redis连接配置;

3、修改server/hsw_server.php里的define('DOMAIN', 'http://192.168.79.206:8081'); 为client目录所对应的站点域名;

4、命令行执行 ：

> php /path/start.php

5、[nginx配置](https://github.com/a1554610616/swoole_webim_cluster/blob/master/upstream.conf)

