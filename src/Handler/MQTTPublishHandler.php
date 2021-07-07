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
namespace Hyperf\MqttServer\Handler;

use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ServerRequestInterface;
use Simps\MQTT\Protocol\Types;
use Simps\MQTT\Protocol\V3;
use Swoole\Server;

class MQTTPublishHandler implements HandlerInterface
{
    public function handle(ServerRequestInterface $request, Response $response): Response
    {
        /** @var Server $server */
        $server = $response->getAttribute('server');
        $fd = $request->getAttribute('fd');
        $data = $request->getParsedBody();
        foreach ($server->connections as $targetFd) {
            if ($targetFd != $fd) {
                $server->send(
                    $targetFd,
                    V3::pack(
                        [
                            'type' => $data['type'],
                            'topic' => $data['topic'],
                            'message' => $data['message'],
                            'dup' => $data['dup'],
                            'qos' => $data['qos'],
                            'retain' => $data['retain'],
                            'message_id' => $data['message_id'] ?? '',
                        ]
                    )
                );
            }
        }

        if ($data['qos'] === 1) {
            $response = $response->withBody(new SwooleStream(V3::pack(
                [
                    'type' => Types::PUBACK,
                    'message_id' => $data['message_id'] ?? '',
                ]
            )));
        }

        return $response;
    }
}
