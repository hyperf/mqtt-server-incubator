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

        return $response->withBody(new SwooleStream(V3::pack(
            [
                'type' => Types::SUBACK,
                'message_id' => $data['message_id'] ?? '',
                'codes' => $payload,
            ]
        )));
    }
}
