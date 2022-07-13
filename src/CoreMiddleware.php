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
namespace Hyperf\MqttServer;

use Hyperf\Context\Context;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpMessage\Base\Response;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\MqttServer\Annotation\MQTTEvent;
use Hyperf\MqttServer\Handler\MQTTConnectHandler;
use Hyperf\MqttServer\Handler\MQTTDisconnectHandler;
use Hyperf\MqttServer\Handler\MQTTPingReqHandler;
use Hyperf\MqttServer\Handler\MQTTPublishHandler;
use Hyperf\MqttServer\Handler\MQTTSubscribeHandler;
use Hyperf\MqttServer\Handler\MQTTUnsubscribeHandler;
use Laminas\Stdlib\SplPriorityQueue;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simps\MQTT\Protocol\Types;

class CoreMiddleware implements CoreMiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $serverName;

    public function __construct(ContainerInterface $container, string $serverName)
    {
        $this->container = $container;
        $this->serverName = $serverName;
    }

    public function dispatch(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $type = $request->getAttribute(Types::class);
        $items = AnnotationCollector::getMethodsByAnnotation(MQTTEvent::class);
        $response = Context::get(ResponseInterface::class);

        $queue = new SplPriorityQueue();
        foreach ($items as $item) {
            $class = $item['class'];
            $method = $item['method'];
            /** @var MQTTEvent $annotation */
            $annotation = $item['annotation'];
            if ($annotation->server === $this->serverName && $annotation->type === $type) {
                $queue->insert([$class, $method], $annotation->priority);
            }
        }

        if ($defaultHandler = $this->getDefaultHandler($type)) {
            $queue->insert([$defaultHandler, 'handle'], 0);
        }

        foreach ($queue as [$class, $method]) {
            if (! $this->container->has($class)) {
                continue;
            }

            $response = $this->container->get($class)->{$method}($request, $response);
            if ($response instanceof Response && $response->getAttribute('stopped', false)) {
                break;
            }
        }

        return $response;
    }

    protected function getDefaultHandler(int $type): ?string
    {
        $handlers = [
            Types::CONNECT => MQTTConnectHandler::class,
            Types::DISCONNECT => MQTTDisconnectHandler::class,
            Types::PINGREQ => MQTTPingReqHandler::class,
            // The handlers below are not necessary.
            // Types::PUBLISH => MQTTPublishHandler::class,
            // Types::SUBSCRIBE => MQTTSubscribeHandler::class,
            // Types::UNSUBSCRIBE => MQTTUnsubscribeHandler::class,
        ];

        return $handlers[$type] ?? null;
    }
}
