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

use Hyperf\Context\Context;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Utils\ApplicationContext;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Simps\MQTT\Protocol\ProtocolInterface;
use Simps\MQTT\Protocol\Types;
use Simps\MQTT\Protocol\V3;
use Simps\MQTT\Protocol\V5;
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

        foreach ($server->connections as $targetFd) {
            if ($targetFd != $fd) {
                $protocolLevel = ApplicationContext::getContainer()->get(CacheInterface::class)->get('MqttProtocolLevel:' . $targetFd);
                $packData =
                    [
                        'type' => $data['type'],
                        'topic' => $data['topic'],
                        'message' => $data['message'],
                        'dup' => $data['dup'],
                        'qos' => $data['qos'],
                        'retain' => $data['retain'],
                        'message_id' => $data['message_id'] ?? '',
                    ];
                if ($protocolLevel != ProtocolInterface::MQTT_PROTOCOL_LEVEL_5_0) {
                    $packData = V3::pack($packData);
                } else {
                    $packData = V5::pack($packData);
                }
                $server->send(
                    $targetFd,
                    $packData
                );
            }
        }

        if ($data['qos'] === 1) {
            $protocolLevel = $response->getAttribute('MqttProtocolLevel');
            $packData = [
                'type' => Types::PUBACK,
                'message_id' => $data['message_id'] ?? '',
            ];
            if ($protocolLevel != ProtocolInterface::MQTT_PROTOCOL_LEVEL_5_0) {
                $packData = V3::pack($packData);
            } else {
                $packData = V5::pack($packData);
            }
            $response = $response->withBody(new SwooleStream($packData));
        }

        return $response;
    }
}
