<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Server;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Toadbeatz\SwooleBundle\Metrics\MetricsCollector;

/**
 * HTTP Server Manager for handling Symfony requests via Swoole
 */
class HttpServerManager
{
    private SwooleServer $swooleServer;
    private KernelInterface $kernel;
    private $requestStack;
    private EventDispatcherInterface $eventDispatcher;
    private ?MetricsCollector $metricsCollector = null;

    public function __construct(
        SwooleServer $swooleServer,
        KernelInterface $kernel,
        $requestStack,
        EventDispatcherInterface $eventDispatcher,
        ?MetricsCollector $metricsCollector = null
    ) {
        $this->swooleServer = $swooleServer;
        $this->kernel = $kernel;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->metricsCollector = $metricsCollector;
    }

    public function getServer(): SwooleServer
    {
        return $this->swooleServer;
    }

    public function initialize(): void
    {
        $server = $this->swooleServer->getServer();

        // Handle HTTP requests
        $server->on('request', function ($swooleRequest, $swooleResponse) {
            $this->handleRequest($swooleRequest, $swooleResponse);
        });

        // Worker start event
        $server->on('workerStart', function ($server, $workerId) {
            // Clear opcache in development
            if ($this->kernel->getEnvironment() === 'dev') {
                if (\function_exists('opcache_reset')) {
                    \opcache_reset();
                }
            }
        });

        // Worker stop event
        $server->on('workerStop', function ($server, $workerId) {
            // Cleanup if needed
        });

        // Shutdown event
        $server->on('shutdown', function () {
            // Cleanup
        });
    }

    private function handleRequest($swooleRequest, $swooleResponse): void
    {
        $startTime = \microtime(true);
        $isError = false;

        try {
            // Convert Swoole request to Symfony request
            $request = $this->convertSwooleRequestToSymfony($swooleRequest);

            // Handle the request
            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

            // Send response
            $this->sendResponse($swooleResponse, $response);

            // Terminate if kernel implements TerminableInterface
            if ($this->kernel instanceof TerminableInterface) {
                $this->kernel->terminate($request, $response);
            }
        } catch (\Throwable $e) {
            $isError = true;
            $this->handleException($swooleResponse, $e);
        } finally {
            // Record metrics
            if ($this->metricsCollector !== null) {
                $responseTime = \microtime(true) - $startTime;
                $this->metricsCollector->recordRequest($responseTime, $isError);
            }
        }
    }

    private function convertSwooleRequestToSymfony($swooleRequest): Request
    {
        // Parse headers
        $headers = $swooleRequest->header ?? [];
        $server = $swooleRequest->server ?? [];

        // Convert Swoole server variables to PHP $_SERVER format
        $serverVars = [];
        foreach ($server as $key => $value) {
            $serverVars[\strtoupper($key)] = $value;
        }

        // Add headers to server vars
        foreach ($headers as $key => $value) {
            $key = 'HTTP_' . \strtoupper(\str_replace('-', '_', $key));
            $serverVars[$key] = $value;
        }

        // Set basic server vars
        $serverVars['REQUEST_METHOD'] = $server['request_method'] ?? 'GET';
        $serverVars['REQUEST_URI'] = $server['request_uri'] ?? '/';
        $serverVars['SERVER_PROTOCOL'] = $server['server_protocol'] ?? 'HTTP/1.1';
        $serverVars['SERVER_NAME'] = $server['server_name'] ?? 'localhost';
        $serverVars['SERVER_PORT'] = $server['server_port'] ?? 80;
        $serverVars['QUERY_STRING'] = $server['query_string'] ?? '';

        // Create Symfony request
        $request = Request::create(
            $serverVars['REQUEST_URI'],
            $serverVars['REQUEST_METHOD'],
            $swooleRequest->get ?? [],
            $swooleRequest->cookie ?? [],
            $swooleRequest->files ?? [],
            $serverVars,
            $swooleRequest->rawContent() ?: null
        );

        // Set headers
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return $request;
    }

    private function sendResponse($swooleResponse, Response $response): void
    {
        // Set status code
        $swooleResponse->status($response->getStatusCode());

        // Set headers
        foreach ($response->headers->all() as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->header($name, $value);
            }
        }

        // Remove Content-Length header as Swoole will set it
        $swooleResponse->header('Content-Length', (string) \strlen($response->getContent()));

        // Send cookies
        foreach ($response->headers->getCookies() as $cookie) {
            $swooleResponse->cookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                $cookie->getSameSite()
            );
        }

        // Send content
        $swooleResponse->end($response->getContent());
    }

    private function handleException($swooleResponse, \Throwable $e): void
    {
        $swooleResponse->status(500);
        $swooleResponse->header('Content-Type', 'text/plain');
        
        if ($this->kernel->isDebug()) {
            $swooleResponse->end(
                \sprintf(
                    "Error: %s\nFile: %s\nLine: %d\n\n%s",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                )
            );
        } else {
            $swooleResponse->end('Internal Server Error');
        }
    }
}

