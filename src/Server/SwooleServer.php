<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Server;

use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Runtime;
use Toadbeatz\SwooleBundle\Debug\DebugHandler;

/**
 * Swoole Server wrapper for Symfony
 */
class SwooleServer
{
    private SwooleHttpServer $server;
    private array $httpsConfig;
    private array $performanceConfig;
    private array $debugConfig;
    private DebugHandler $debugHandler;
    private array $taskConfig;

    public function __construct(
        string $host,
        int $port,
        array $options,
        array $httpsConfig,
        array $performanceConfig,
        array $debugConfig,
        array $taskConfig = []
    ) {
        $this->httpsConfig = $httpsConfig;
        $this->performanceConfig = $performanceConfig;
        $this->debugConfig = $debugConfig;
        $this->taskConfig = $taskConfig;
        $this->debugHandler = new DebugHandler(
            $debugConfig['enabled'] ?? false,
            $debugConfig['enable_dd'] ?? true,
            $debugConfig['enable_var_dump'] ?? true
        );

        // Initialize debug handler
        $this->debugHandler->initialize();

        // Create HTTP server
        $this->server = new SwooleHttpServer($host, $port, \SWOOLE_PROCESS);

        // Enable coroutines if configured
        if ($this->performanceConfig['enable_coroutine'] ?? true) {
            $hookFlags = $this->performanceConfig['coroutine_hook_flags'] ?? \SWOOLE_HOOK_ALL;
            if ($hookFlags !== null) {
                Runtime::enableCoroutine($hookFlags, true);
            } else {
                Runtime::enableCoroutine(\SWOOLE_HOOK_ALL, true);
            }
        }

        // Configure server options
        $this->configureServer($options);
    }

    private function configureServer(array $options): void
    {
        $serverOptions = [
            'worker_num' => $this->performanceConfig['worker_num'] ?? \swoole_cpu_num(),
            'task_worker_num' => $this->taskConfig['worker_num'] ?? $this->performanceConfig['task_worker_num'] ?? 2,
            'max_request' => $this->performanceConfig['max_request'] ?? 10000,
            'task_max_request' => $this->taskConfig['max_request'] ?? $this->performanceConfig['task_max_request'] ?? 10000,
            'enable_coroutine' => $this->performanceConfig['enable_coroutine'] ?? true,
            'max_coroutine' => $this->performanceConfig['max_coroutine'] ?? 100000,
            'log_level' => \SWOOLE_LOG_INFO,
            'log_file' => \sys_get_temp_dir() . '/swoole.log',
            'daemonize' => false,
            'pid_file' => \sys_get_temp_dir() . '/swoole.pid',
        ];

        // HTTP/2 and WebSocket support
        if ($options['open_http2_protocol'] ?? false) {
            $serverOptions['open_http2_protocol'] = true;
        }

        if ($options['open_websocket_protocol'] ?? false) {
            $serverOptions['open_websocket_protocol'] = true;
        }

        // Static file handler
        if ($options['enable_static_handler'] ?? false) {
            $serverOptions['enable_static_handler'] = true;
            if (isset($options['document_root'])) {
                $serverOptions['document_root'] = $options['document_root'];
            }
        }

        // HTTPS configuration
        if ($this->httpsConfig['enabled'] ?? false) {
            if (!empty($this->httpsConfig['cert']) && !empty($this->httpsConfig['key'])) {
                $serverOptions['ssl_cert_file'] = $this->httpsConfig['cert'];
                $serverOptions['ssl_key_file'] = $this->httpsConfig['key'];
            }
        }

        $this->server->set($serverOptions);
    }

    public function getServer(): SwooleHttpServer
    {
        return $this->server;
    }

    public function start(): void
    {
        $this->server->start();
    }

    public function on(string $event, callable $callback): void
    {
        $this->server->on($event, $callback);
    }

    public function addListener(string $host, int $port, int $type = \SWOOLE_SOCK_TCP): mixed
    {
        return $this->server->addListener($host, $port, $type);
    }
}

