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

class MQTTConnectHandler implements HandlerInterface
{
    use ResponseRewritable;

    public function handle(ServerRequestInterface $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($data['protocol_name'] != 'MQTT') {
            return $response->withAttribute('closed', true);
        }

        if (! $this->isRewritable($response)) {
            return $response;
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
