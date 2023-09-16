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
use Simps\MQTT\Message\PubAck;
use Simps\MQTT\Message\Publish;
use Simps\MQTT\Protocol\ProtocolInterface;
use Swoole\Coroutine\Server\Connection;
use Swoole\Server;

class MQTTPublishHandler implements HandlerInterface
{
    public function handle(ServerRequestInterface $request, Response $response): Response
    {
        /** @var Connection|Server $server */
        $server = $response->getAttribute('server');
        $fd = $request->getAttribute('fd');
        $data = $request->getParsedBody();
        $level = $request->getAttribute(ProtocolInterface::class);

        $responseData = [
            'protocolLevel' => $level,
            'messageId' => $data['message_id'],
            'type' => $data['type'],
            'topic' => $data['topic'],
            'message' => $data['message'],
            'dup' => $data['dup'],
            'qos' => $data['qos'],
            'retain' => $data['retain'],
        ];

        $ack = new Publish($responseData);

        foreach ($server->connections as $targetFd) {
            if ($targetFd != $fd) {
                $server->send($targetFd, (string) $ack);
            }
        }

        if ($data['qos'] === 1) {
            $ack = new PubAck($responseData);
            $response = $response->withBody(new SwooleStream((string) $ack));
        }

        return $response;
    }
}
