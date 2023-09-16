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
use Simps\MQTT\Hex\ReasonCode;
use Simps\MQTT\Message\UnSubAck;
use Simps\MQTT\Protocol\ProtocolInterface;

class MQTTUnsubscribeHandler implements HandlerInterface
{
    public function handle(ServerRequestInterface $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $level = $request->getAttribute(ProtocolInterface::class);

        $payload = [];
        foreach ($data['topics'] as $qos) {
            if (is_numeric($qos) && $qos < 3) {
                $payload[] = $qos;
            } else {
                $payload[] = ReasonCode::UNSPECIFIED_ERROR;
            }
        }

        $ack = new UnSubAck();
        $ack->setProtocolLevel($level)->setMessageId($data['message_id'])->setCodes($payload);

        return $response->withBody(new SwooleStream((string) $ack));
    }
}
