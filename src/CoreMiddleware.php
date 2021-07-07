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

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\MqttServer\Annotation\MQTTEvent;
use Hyperf\MqttServer\Handler\MQTTConnectHandler;
use Hyperf\Utils\Context;
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

        switch ($type) {
            case Types::CONNECT:
                $queue->insert([MQTTConnectHandler::class, 'handle'], 0);
                break;
        }

        foreach ($queue as [$class, $method]) {
            if ($this->container->has($class)) {
                $response = $this->container->get($class)->{$method}($request, $response);
            }
        }

        return $response;
    }
}
