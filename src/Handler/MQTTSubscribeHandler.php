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
use Psr\Http\Message\ServerRequestInterface;
use Simps\MQTT\Protocol\ProtocolInterface;
use Simps\MQTT\Protocol\Types;
use Simps\MQTT\Protocol\V3;
use Simps\MQTT\Protocol\V5;

class MQTTSubscribeHandler implements HandlerInterface
{
    public function handle(ServerRequestInterface $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $payload = [];
        foreach ($data['topics'] as $k => $qos) {
            if (is_numeric($qos) && $qos < 3) {
                $payload[] = $qos;
            } else {
                $payload[] = 0x80;
            }
        }
        $data = [
            'type' => Types::SUBACK,
            'message_id' => $data['message_id'] ?? '',
            'codes' => $payload,
        ];

        if (Context::get('MqttProtocolLevel') != ProtocolInterface::MQTT_PROTOCOL_LEVEL_5_0) {
            $data = V3::pack($data);
        } else {
            $data = V5::pack($data);
        }

        return $response->withBody(new SwooleStream($data));
    }
}
