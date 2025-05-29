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

use Hyperf\Codec\Json;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\DispatcherInterface;
use Hyperf\Contract\MiddlewareInitializerInterface;
use Hyperf\Contract\OnReceiveInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Dispatcher\HttpDispatcher;
use Hyperf\ExceptionHandler\ExceptionHandlerDispatcher;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response as PsrResponse;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpMessage\Uri\Uri;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\MqttServer\Exception\Handler\MqttExceptionHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Simps\MQTT\Protocol\ProtocolInterface;
use Simps\MQTT\Protocol\Types;
use Simps\MQTT\Protocol\V3;
use Simps\MQTT\Protocol\V5;
use Simps\MQTT\Tools\UnPackTool;
use Swoole\Coroutine\Server\Connection;
use Swoole\Server as SwooleServer;
use Throwable;

class MQTTServer implements OnReceiveInterface, MiddlewareInitializerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var DispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var ExceptionHandlerDispatcher
     */
    protected $exceptionHandlerDispatcher;

    /**
     * @var array
     */
    protected $middlewares;

    /**
     * @var CoreMiddlewareInterface
     */
    protected $coreMiddleware;

    /**
     * @var array
     */
    protected $exceptionHandlers;

    /**
     * @var string
     */
    protected $serverName;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        ContainerInterface $container,
        HttpDispatcher $dispatcher,
        ExceptionHandlerDispatcher $exceptionDispatcher,
        StdoutLoggerInterface $logger
    ) {
        $this->container = $container;
        $this->dispatcher = $dispatcher;
        $this->exceptionHandlerDispatcher = $exceptionDispatcher;
        $this->logger = $logger;
    }

    public function initCoreMiddleware(string $serverName): void
    {
        $this->serverName = $serverName;
        $this->coreMiddleware = $this->createCoreMiddleware();

        $config = $this->container->get(ConfigInterface::class);
        $this->middlewares = $config->get('middlewares.' . $serverName, []);
        $this->exceptionHandlers = $config->get('exceptions.handler.' . $serverName, $this->getDefaultExceptionHandler());
    }

    public function onReceive($server, int $fd, int $reactorId, string $data): void
    {
        try {
            Context::set(ResponseInterface::class, $this->buildResponse($fd, $server));

            $cache = $this->container->get(CacheInterface::class);
            $protocolLevelKey = ProtocolInterface::class . $fd;

            $protocolLevel = $cache->get($protocolLevelKey);
            if (UnPackTool::getType($data) == Types::CONNECT) {
                $cache->set($protocolLevelKey, $protocolLevel = UnPackTool::getLevel($data));
            }

            CoordinatorManager::until(Constants::WORKER_START)->yield();

            // Initialize PSR-7 Request and Response objects.
            Context::set(ServerRequestInterface::class, $request = $this->buildRequest($fd, $reactorId, $data, $protocolLevel));

            $middlewares = $this->middlewares;

            $request = $this->coreMiddleware->dispatch($request);

            $response = $this->dispatcher->dispatch($request, $middlewares, $this->coreMiddleware);
        } catch (Throwable $throwable) {
            // Delegate the exception to exception handler.
            $exceptionHandlerDispatcher = $this->container->get(ExceptionHandlerDispatcher::class);
            $response = $exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
        } finally {
            if ($response instanceof PsrResponse && $response->getAttribute('closed', false)) {
                $cache->delete($protocolLevelKey);
                $this->close($server, $fd);
            }
            if ($response instanceof ResponseInterface) {
                $this->send($server, $fd, $response);
            }
        }
    }

    protected function getDefaultExceptionHandler(): array
    {
        return [
            MqttExceptionHandler::class,
        ];
    }

    /**
     * @param Connection|SwooleServer $server
     */
    protected function send($server, int $fd, ResponseInterface $response): void
    {
        $body = (string) $response->getBody();
        if (empty($body)) {
            return;
        }

        if ($server instanceof SwooleServer) {
            $server->send($fd, $body);
        } elseif ($server instanceof Connection) {
            $server->send($body);
        }
    }

    protected function close($server, int $fd): void
    {
        if ($server instanceof SwooleServer) {
            $server->close($fd);
        } elseif ($server instanceof Connection) {
            $server->close();
        }
    }

    protected function createCoreMiddleware(): CoreMiddlewareInterface
    {
        return new CoreMiddleware($this->container, $this->serverName);
    }

    protected function buildRequest(int $fd, int $reactorId, string $data, int $protocolLevel): ServerRequestInterface
    {
        $data = $protocolLevel !== ProtocolInterface::MQTT_PROTOCOL_LEVEL_5_0 ? V3::unpack($data) : V5::unpack($data);
        $uri = new Uri('http://0.0.0.0/');
        $request = new Request('POST', $uri, ['Content-Type' => 'application/json'], new SwooleStream(Json::encode($data)));
        return $request->withAttribute(Types::class, $data['type'] ?? 0)
            ->withAttribute('fd', $fd)
            ->withAttribute('reactorId', $reactorId)
            ->withAttribute(ProtocolInterface::class, $protocolLevel)
            ->withParsedBody($data);
    }

    protected function buildResponse(int $fd, $server): ResponseInterface
    {
        return (new PsrResponse())->withAttribute('fd', $fd)->withAttribute('server', $server);
    }
}
