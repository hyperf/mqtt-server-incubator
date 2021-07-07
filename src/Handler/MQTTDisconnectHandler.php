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
use Psr\Http\Message\ServerRequestInterface;

class MQTTDisconnectHandler implements HandlerInterface
{
    public function handle(ServerRequestInterface $request, Response $response): Response
    {
        return $response->withAttribute('closed', true);
    }
}
