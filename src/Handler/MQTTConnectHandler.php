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
use Simps\MQTT\Message\ConnAck;
use Simps\MQTT\Protocol\ProtocolInterface;

class MQTTConnectHandler implements HandlerInterface
{
    use ResponseRewritable;

    public function handle(ServerRequestInterface $request, Response $response): Response
    {
        $level = $request->getAttribute(ProtocolInterface::class);
        $data = $request->getParsedBody();

        if (! $this->isValidProtocol($level, $data['protocol_name'])) {
            return $response->withAttribute('closed', true);
        }

        if (! $this->isRewritable($response)) {
            return $response;
        }

        $ack = new ConnAck();
        $ack->setProtocolLevel($level)->setCode(0)->setSessionPresent(0);

        return $response->withBody(new SwooleStream((string) $ack));
    }

    private function isValidProtocol($level, $name): bool
    {
        return
            ($level === ProtocolInterface::MQTT_PROTOCOL_LEVEL_3_1_1 && $name === ProtocolInterface::MQTT_PROTOCOL_NAME)
            || ($level === ProtocolInterface::MQTT_PROTOCOL_LEVEL_5_0 && $name === ProtocolInterface::MQTT_PROTOCOL_NAME)
            || ($level === ProtocolInterface::MQTT_PROTOCOL_LEVEL_3_1 && $name === ProtocolInterface::MQISDP_PROTOCOL_NAME);
    }
}
