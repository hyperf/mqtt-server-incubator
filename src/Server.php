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
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\MqttServer\Exception\Handler\MqttExceptionHandler;
use Hyperf\Utils\Context;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Server\Connection;
use Swoole\Server as SwooleServer;
use Throwable;

abstract class Server implements OnReceiveInterface
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
            Context::set(ServerRequestInterface::class, $request = $this->buildRequest($fd, $reactorId, $data));
            Context::set(ResponseInterface::class, $this->buildResponse($fd, $server));

            $middlewares = $this->middlewares;

            $request = $this->coreMiddleware->dispatch($request);

            $response = $this->dispatcher->dispatch($request, $middlewares, $this->coreMiddleware);
        } catch (Throwable $throwable) {
            // Delegate the exception to exception handler.
            $exceptionHandlerDispatcher = $this->container->get(ExceptionHandlerDispatcher::class);
            $response = $exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
        } finally {
            if ($response) {
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
        if ($server instanceof SwooleServer) {
            $server->send($fd, (string) $response->getBody());
        } elseif ($server instanceof Connection) {
            $server->send((string) $response->getBody());
        }
    }

    abstract protected function createCoreMiddleware(): CoreMiddlewareInterface;

    abstract protected function buildRequest(int $fd, int $reactorId, string $data): ServerRequestInterface;

    abstract protected function buildResponse(int $fd, $server): ResponseInterface;
}
