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
 * 
 * This manager bridges Swoole HTTP server with Symfony's kernel,
 * handling request/response conversion and lifecycle events.
 */
class HttpServerManager
{
    private SwooleServer $swooleServer;
    private KernelInterface $kernel;
    private $requestStack;
    private EventDispatcherInterface $eventDispatcher;
    private ?MetricsCollector $metricsCollector = null;

    /** @var array<string, callable> */
    private array $taskHandlers = [];

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

    /**
     * Register a task handler for a specific task type
     */
    public function registerTaskHandler(string $type, callable $handler): void
    {
        $this->taskHandlers[$type] = $handler;
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
            $this->onWorkerStart($server, $workerId);
        });

        // Worker stop event
        $server->on('workerStop', function ($server, $workerId) {
            $this->onWorkerStop($server, $workerId);
        });

        // Shutdown event
        $server->on('shutdown', function () {
            $this->onShutdown();
        });

        // Task worker handler - required when task_worker_num > 0
        $server->on('task', function ($server, $taskId, $reactorId, $data) {
            return $this->handleTask($server, $taskId, $reactorId, $data);
        });

        // Task finish handler - required when task_worker_num > 0
        $server->on('finish', function ($server, $taskId, $data) {
            $this->handleTaskFinish($server, $taskId, $data);
        });

        // Worker error handler
        $server->on('workerError', function ($server, $workerId, $workerPid, $exitCode, $signal) {
            $this->onWorkerError($workerId, $workerPid, $exitCode, $signal);
        });
    }

    /**
     * Handle task execution
     * 
     * @param mixed $server Swoole server instance
     * @param int $taskId Task ID
     * @param int $reactorId Worker ID that sent the task
     * @param mixed $data Task data
     * @return mixed Task result
     */
    private function handleTask($server, int $taskId, int $reactorId, mixed $data): mixed
    {
        try {
            // If data is a callable, execute it directly
            if (\is_callable($data)) {
                return $data();
            }

            // If data is an array with 'type' and 'payload', use registered handlers
            if (\is_array($data) && isset($data['type'])) {
                $type = $data['type'];
                $payload = $data['payload'] ?? [];

                if (isset($this->taskHandlers[$type])) {
                    return ($this->taskHandlers[$type])($payload);
                }

                // Default handler for unknown types
                return [
                    'status' => 'error',
                    'message' => \sprintf('No handler registered for task type: %s', $type),
                ];
            }

            // Return data as-is for simple tasks
            return $data;
        } catch (\Throwable $e) {
            // Log error and return error response
            \error_log(\sprintf(
                '[Swoole Task #%d] Error: %s in %s:%d',
                $taskId,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'task_id' => $taskId,
            ];
        }
    }

    /**
     * Handle task completion
     */
    private function handleTaskFinish($server, int $taskId, mixed $data): void
    {
        // Log successful task completion if in debug mode
        if ($this->kernel->isDebug()) {
            \error_log(\sprintf('[Swoole Task #%d] Completed', $taskId));
        }
    }

    /**
     * Handle worker start event
     * 
     * This is called when a worker process starts or after a reload.
     * We clear OPcache here to ensure new code is loaded.
     */
    private function onWorkerStart($server, int $workerId): void
    {
        // Clear OPcache to ensure fresh code is loaded after reload
        // This is especially important in production after a reload
        if (\function_exists('opcache_reset')) {
            \opcache_reset();
        }

        // Also invalidate common autoload files
        if (\function_exists('opcache_invalidate')) {
            $projectDir = $this->kernel->getProjectDir();
            $filesToInvalidate = [
                $projectDir . '/vendor/autoload.php',
                $projectDir . '/config/services.php',
                $projectDir . '/config/bundles.php',
            ];
            
            foreach ($filesToInvalidate as $file) {
                if (\file_exists($file)) {
                    \opcache_invalidate($file, true);
                }
            }
        }

        // Clear Symfony cache in development
        if ($this->kernel->getEnvironment() === 'dev') {
            // Additional dev-specific cleanup can go here
        }

        // Start metrics collection timer if available (only for first worker)
        if ($this->metricsCollector !== null && $workerId === 0) {
            $this->metricsCollector->startCollecting();
        }
    }

    /**
     * Handle worker stop event
     */
    private function onWorkerStop($server, int $workerId): void
    {
        // Stop metrics collection if this is the first worker
        if ($this->metricsCollector !== null && $workerId === 0) {
            $this->metricsCollector->stopCollecting();
        }
    }

    /**
     * Handle server shutdown event
     */
    private function onShutdown(): void
    {
        // Cleanup resources
    }

    /**
     * Handle worker error event
     */
    private function onWorkerError(int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        \error_log(\sprintf(
            '[Swoole Worker #%d] Error - PID: %d, Exit Code: %d, Signal: %d',
            $workerId,
            $workerPid,
            $exitCode,
            $signal
        ));
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

        // Validate and sanitize input to prevent header injection
        $maxHeaderLength = 8192; // RFC 7230 limit
        $maxUriLength = 8192; // RFC 7230 limit

        // Convert Swoole server variables to PHP $_SERVER format
        $serverVars = [];
        foreach ($server as $key => $value) {
            // Sanitize key
            $key = \strtoupper((string) $key);
            // Sanitize value - prevent header injection
            $value = \is_string($value) ? \substr((string) $value, 0, $maxHeaderLength) : $value;
            $serverVars[$key] = $value;
        }

        // Add headers to server vars with security validation
        foreach ($headers as $key => $value) {
            // Prevent CRLF injection in header names and values
            $key = \str_replace(["\r", "\n", "\0"], '', (string) $key);
            $value = \str_replace(["\r", "\n", "\0"], '', (string) $value);
            
            // Validate header length
            if (\strlen($key) > $maxHeaderLength || \strlen($value) > $maxHeaderLength) {
                continue; // Skip invalid headers
            }
            
            $normalizedKey = 'HTTP_' . \strtoupper(\str_replace('-', '_', $key));
            $serverVars[$normalizedKey] = $value;
        }

        // Set basic server vars with validation
        $requestUri = $server['request_uri'] ?? '/';
        // Remove null bytes and CRLF to prevent injection
        $requestUri = \str_replace(["\0", "\r", "\n"], '', (string) $requestUri);
        // Validate URI length
        if (\strlen($requestUri) > $maxUriLength) {
            $requestUri = \substr($requestUri, 0, $maxUriLength);
        }
        
        $serverVars['REQUEST_METHOD'] = \strtoupper($server['request_method'] ?? 'GET');
        $serverVars['REQUEST_URI'] = $requestUri;
        $serverVars['SERVER_PROTOCOL'] = $server['server_protocol'] ?? 'HTTP/1.1';
        $serverVars['SERVER_NAME'] = $server['server_name'] ?? 'localhost';
        $serverVars['SERVER_PORT'] = (int) ($server['server_port'] ?? 80);
        $serverVars['QUERY_STRING'] = \substr((string) ($server['query_string'] ?? ''), 0, $maxHeaderLength);

        // Handle content type for POST requests
        if (isset($headers['content-type'])) {
            $serverVars['CONTENT_TYPE'] = $headers['content-type'];
        }
        if (isset($headers['content-length'])) {
            $serverVars['CONTENT_LENGTH'] = $headers['content-length'];
        }

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

        // Set POST data if present
        if (!empty($swooleRequest->post)) {
            $request->request->replace($swooleRequest->post);
        }

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

        // Add security headers if not already set (only in production)
        if (!$this->kernel->isDebug()) {
            $securityHeaders = [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-XSS-Protection' => '1; mode=block',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
            ];
            
            foreach ($securityHeaders as $header => $value) {
                if (!$response->headers->has($header)) {
                    $swooleResponse->header($header, $value);
                }
            }
        }

        // Get content
        $content = $response->getContent();

        // Set Content-Length header
        $swooleResponse->header('Content-Length', (string) \strlen($content));

        // Send cookies
        foreach ($response->headers->getCookies() as $cookie) {
            $swooleResponse->cookie(
                $cookie->getName(),
                $cookie->getValue() ?? '',
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain() ?? '',
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                $cookie->getSameSite() ?? ''
            );
        }

        // Send content
        $swooleResponse->end($content);
    }

    private function handleException($swooleResponse, \Throwable $e): void
    {
        $swooleResponse->status(500);
        $swooleResponse->header('Content-Type', 'text/html; charset=UTF-8');
        
        if ($this->kernel->isDebug()) {
            $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Error</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f5f5f5; }
        .error { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .title { color: #c0392b; margin: 0 0 10px; }
        .message { color: #333; }
        .trace { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; margin-top: 15px; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="error">
        <h1 class="title">Error</h1>
        <p class="message">{$e->getMessage()}</p>
        <p><strong>File:</strong> {$e->getFile()}:{$e->getLine()}</p>
        <div class="trace"><pre>{$e->getTraceAsString()}</pre></div>
    </div>
</body>
</html>
HTML;
            $swooleResponse->end($html);
        } else {
            $swooleResponse->end('<h1>Internal Server Error</h1><p>An error occurred while processing your request.</p>');
        }
    }
}
