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

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\DispatcherInterface;
use Hyperf\Contract\OnReceiveInterface;
use Hyperf\ExceptionHandler\ExceptionHandlerDispatcher;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response as PsrResponse;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpMessage\Uri\Uri;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\MqttServer\Exception\Handler\MqttExceptionHandler;
use Hyperf\Utils\Codec\Json;
use Hyperf\Utils\Context;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Simps\MQTT\Protocol\Types;
use Simps\MQTT\Protocol\V3;
use Swoole\Coroutine\Server\Connection;
use Swoole\Server as SwooleServer;
use Throwable;

class MQTTServer implements OnReceiveInterface
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
        DispatcherInterface $dispatcher,
        ExceptionHandlerDispatcher $exceptionDispatcher,
        LoggerInterface $logger
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
        $request = $response = null;
        try {
            CoordinatorManager::until(Constants::WORKER_START)->yield();

            // Initialize PSR-7 Request and Response objects.
            Context::set(ResponseInterface::class, $this->buildResponse($fd, $server));
            Context::set(ServerRequestInterface::class, $request = $this->buildRequest($fd, $reactorId, $data));

            $middlewares = $this->middlewares;

            $request = $this->coreMiddleware->dispatch($request);

            $response = $this->dispatcher->dispatch($request, $middlewares, $this->coreMiddleware);
        } catch (Throwable $throwable) {
            // Delegate the exception to exception handler.
            $exceptionHandlerDispatcher = $this->container->get(ExceptionHandlerDispatcher::class);
            $response = $exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
        } finally {
            if ($response instanceof PsrResponse && $response->getAttribute('closed', false)) {
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

    protected function buildRequest(int $fd, int $reactorId, string $data): ServerRequestInterface
    {
        $data = V3::unpack($data);
        $uri = new Uri('http://0.0.0.0/');
        $request = new Request('POST', $uri, ['Content-Type' => 'application/json'], new SwooleStream(Json::encode($data)));
        return $request->withAttribute(Types::class, $data['type'] ?? 0)
            ->withAttribute('fd', $fd)
            ->withAttribute('reactorId', $reactorId)
            ->withParsedBody($data);
    }

    protected function buildResponse(int $fd, $server): ResponseInterface
    {
        return (new PsrResponse())->withAttribute('fd', $fd)->withAttribute('server', $server);
    }
}
