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

use Hyperf\HttpMessage\Base\Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\MqttServer\Exception\InvalidProtocolException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Simps\MQTT\Protocol\Types;
use Simps\MQTT\Protocol\V3;

class MQTTConnectHandler implements HandlerInterface
{
    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        if ($data['protocol_name'] != 'MQTT') {
            if ($response instanceof Response) {
                return $response->withAttribute('closed', true);
            }

            throw new InvalidProtocolException('Protocol is invalid.');
        }

        return $response->withBody(new SwooleStream(V3::pack(
            [
                'type' => Types::CONNACK,
                'code' => 0,
                'session_present' => 0,
            ]
        )));
    }
}
