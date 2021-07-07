# MQTT Server

## 安装

```bash
composer require hyperf/mqtt-server-incubator
```

## 配置服务

```php
<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\Server\Event;
use Hyperf\Server\Server;

return [
    'mode' => SWOOLE_BASE,
    'servers' => [
        [
            'name' => 'mqtt',
            'type' => Server::SERVER_BASE,
            'host' => '0.0.0.0',
            'port' => 1883,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_RECEIVE => [Hyperf\MqttServer\MQTTServer::class, 'onReceive'],
            ],
        ],
    ],
    'settings' => [
        'enable_coroutine' => true,
        'worker_num' => 4,
        'pid_file' => BASE_PATH . '/runtime/hyperf.pid',
        'open_tcp_nodelay' => true,
        'max_coroutine' => 100000,
        'open_http2_protocol' => true,
        'max_request' => 0,
        'socket_buffer_size' => 2 * 1024 * 1024,
        'package_max_length' => 2 * 1024 * 1024,
    ],
    'callbacks' => [
        Event::ON_BEFORE_START => [Hyperf\Framework\Bootstrap\ServerStartCallback::class, 'beforeStart'],
        Event::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
    ],
];

```

启动服务，我们就可以简单的使用 MQTT 服务了。

## 自定义事件

组件增加了可以监听 MQTT 服务各个阶段的事件，比如我们写一个 `MQTTConnectHandler` 用来监听客户端连接。

```php
<?php

declare(strict_types=1);

namespace App\MQTT\Event;

use Hyperf\HttpMessage\Server\Response;
use Hyperf\MqttServer\Annotation\MQTTConnect;
use Hyperf\MqttServer\Handler\HandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

#[MQTTConnect(priority: 1)]
class MQTTConnectHandler implements HandlerInterface
{
    public function handle(ServerRequestInterface $request, Response $response): Response
    {
        var_dump((string) $request->getBody());
        return $response;
    }
}
```

重启服务，连接 MQTT 时，便可以得到以下输出。

```
$ php bin/hyperf.php start
[INFO] TCP Server listening at 0.0.0.0:1883
string(234) "{"type":1,"protocol_name":"MQTT","protocol_level":4,"clean_session":1,"will":{"qos":0,"retain":0,"topic":"simps-mqtt\/user001\/delete","message":"byebye"},"user_name":"","password":"","keep_alive":10,"client_id":"Simps_60e5aa0c4284f"}"
```

组件支持的事件列表如下：

|      事件       |         备注         |
| :-------------: | :------------------: |
|   MQTTConnect   |   客户端连接时触发   |
| MQTTDisconnect  | 客户端断开连接时触发 |
|   MQTTPingReq   |                      |
|   MQTTPublish   | 客户端发布消息时触发 |
|  MQTTSubscribe  |   客户端订阅时触发   |
| MQTTUnsubscribe | 客户端取消订阅时触发 |

注解支持参数如下

|   参数   |                      备注                      |
| :------: | :--------------------------------------------: |
|  server  |            指定当前事件对应的服务名            |
|   type   |                    事件类型                    |
| priority | 事件优先级，越大越先执行，默认的事件优先级为 0 |

## 需要完善的难点

- 支持协程风格
- 支持分布式
