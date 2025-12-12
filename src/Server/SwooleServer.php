<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Server;

use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Runtime;
use Toadbeatz\SwooleBundle\Debug\DebugHandler;

/**
 * Swoole Server wrapper for Symfony 7/8
 * 
 * Exploits ALL Swoole 6.1.4 capabilities for maximum performance.
 * Fully compatible with Swoole 6.1+ API changes.
 * 
 * @since Swoole 6.1
 * @compatible Symfony 7.0, 8.0
 */
class SwooleServer
{
    private SwooleHttpServer $server;
    private array $httpsConfig;
    private array $performanceConfig;
    private array $debugConfig;
    private DebugHandler $debugHandler;
    private array $taskConfig;
    private array $http2Config;

    public function __construct(
        string $host,
        int $port,
        array $options,
        array $httpsConfig,
        array $performanceConfig,
        array $debugConfig,
        array $taskConfig = [],
        array $http2Config = []
    ) {
        $this->httpsConfig = $httpsConfig;
        $this->performanceConfig = $performanceConfig;
        $this->debugConfig = $debugConfig;
        $this->taskConfig = $taskConfig;
        $this->http2Config = $http2Config;
        $this->debugHandler = new DebugHandler(
            $debugConfig['enabled'] ?? false,
            $debugConfig['enable_dd'] ?? true,
            $debugConfig['enable_var_dump'] ?? true
        );

        // Initialize debug handler
        $this->debugHandler->initialize();

        // Determine server mode based on configuration
        $serverMode = $this->determineServerMode();

        // Create HTTP server with appropriate mode
        $this->server = new SwooleHttpServer($host, $port, $serverMode);

        // Enable coroutines if configured
        // Note: Swoole 6.1+ only accepts one argument for enableCoroutine()
        if ($this->performanceConfig['enable_coroutine'] ?? true) {
            $hookFlags = $this->performanceConfig['coroutine_hook_flags'] ?? \SWOOLE_HOOK_ALL;
            Runtime::enableCoroutine($hookFlags);
        }

        // Configure server options
        $this->configureServer($options);
    }

    /**
     * Determine server mode (Process, Thread, Base)
     */
    private function determineServerMode(): int
    {
        // Check if thread mode is requested and available (Swoole 6.0+)
        if (($this->performanceConfig['thread_mode'] ?? false) && \defined('SWOOLE_THREAD')) {
            return \SWOOLE_THREAD;
        }

        // Check if base mode is requested (single process)
        if ($this->performanceConfig['base_mode'] ?? false) {
            return \SWOOLE_BASE;
        }

        // Default to process mode
        return \SWOOLE_PROCESS;
    }

    private function configureServer(array $options): void
    {
        $serverOptions = [
            // Worker configuration
            'worker_num' => $this->performanceConfig['worker_num'] ?? \swoole_cpu_num(),
            'task_worker_num' => $this->taskConfig['worker_num'] ?? $this->performanceConfig['task_worker_num'] ?? 2,
            'max_request' => $this->performanceConfig['max_request'] ?? 10000,
            'task_max_request' => $this->taskConfig['max_request'] ?? $this->performanceConfig['task_max_request'] ?? 10000,
            
            // Coroutine configuration
            'enable_coroutine' => $this->performanceConfig['enable_coroutine'] ?? true,
            'max_coroutine' => $this->performanceConfig['max_coroutine'] ?? 100000,
            
            // Memory optimization
            'buffer_output_size' => $this->performanceConfig['buffer_output_size'] ?? 32 * 1024 * 1024,
            'socket_buffer_size' => $this->performanceConfig['socket_buffer_size'] ?? 128 * 1024 * 1024,
            'package_max_length' => $this->performanceConfig['package_max_length'] ?? 10 * 1024 * 1024,
            
            // Connection settings
            'max_connection' => $this->performanceConfig['max_connection'] ?? 10000,
            'max_wait_time' => $this->performanceConfig['max_wait_time'] ?? 60,
            'reload_async' => true,
            
            // Logging
            'log_level' => $this->debugConfig['enabled'] ? \SWOOLE_LOG_DEBUG : \SWOOLE_LOG_INFO,
            'log_file' => \sys_get_temp_dir() . '/swoole.log',
            'log_rotation' => \SWOOLE_LOG_ROTATION_DAILY,
            'log_date_format' => '%Y-%m-%d %H:%M:%S',
            
            // Process settings
            'daemonize' => $this->performanceConfig['daemonize'] ?? false,
            'pid_file' => \sys_get_temp_dir() . '/swoole.pid',
            
            // Heartbeat
            'heartbeat_check_interval' => $this->performanceConfig['heartbeat_interval'] ?? 30,
            'heartbeat_idle_time' => $this->performanceConfig['heartbeat_idle_time'] ?? 60,
        ];

        // HTTP/2 support
        // Note: Swoole 6.1 removed fine-grained HTTP/2 options, only open_http2_protocol is supported
        if ($options['open_http2_protocol'] ?? false) {
            $serverOptions['open_http2_protocol'] = true;
            // HTTP/2 fine-tuning options removed for Swoole 6.1+ compatibility
            // Options like http2_header_table_size, http2_initial_window_size, etc.
            // are no longer supported in Swoole 6.1
        }

        // WebSocket support
        if ($options['open_websocket_protocol'] ?? false) {
            $serverOptions['open_websocket_protocol'] = true;
            $serverOptions['open_websocket_close_frame'] = true;
            $serverOptions['websocket_compression'] = $this->performanceConfig['websocket_compression'] ?? true;
        }

        // Static file handler
        if ($options['enable_static_handler'] ?? false) {
            $serverOptions['enable_static_handler'] = true;
            if (isset($options['document_root'])) {
                $serverOptions['document_root'] = $options['document_root'];
            }
            // Static file locations
            if (isset($options['static_handler_locations'])) {
                $serverOptions['static_handler_locations'] = $options['static_handler_locations'];
            }
        }

        // Compression
        if ($this->performanceConfig['enable_compression'] ?? true) {
            $serverOptions['http_compression'] = true;
            $serverOptions['http_compression_level'] = $this->performanceConfig['compression_level'] ?? 3;
            $serverOptions['http_compression_min_length'] = $this->performanceConfig['compression_min_length'] ?? 20;
        }

        // HTTPS/SSL configuration
        if ($this->httpsConfig['enabled'] ?? false) {
            if (!empty($this->httpsConfig['cert']) && !empty($this->httpsConfig['key'])) {
                $serverOptions['ssl_cert_file'] = $this->httpsConfig['cert'];
                $serverOptions['ssl_key_file'] = $this->httpsConfig['key'];
                
                // Modern TLS settings
                $serverOptions['ssl_protocols'] = $this->httpsConfig['protocols'] ?? 
                    \SWOOLE_SSL_TLSv1_2 | \SWOOLE_SSL_TLSv1_3;
                $serverOptions['ssl_verify_peer'] = $this->httpsConfig['verify_peer'] ?? false;
                
                // HTTP/2 ALPN for HTTPS
                if ($options['open_http2_protocol'] ?? false) {
                    $serverOptions['ssl_ciphers'] = 'EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH';
                }
                
                // Optional CA certificate
                if (!empty($this->httpsConfig['ca'])) {
                    $serverOptions['ssl_ca_file'] = $this->httpsConfig['ca'];
                }
            }
        }

        // TCP settings for performance
        $serverOptions['tcp_fastopen'] = true;
        $serverOptions['tcp_defer_accept'] = 3;
        $serverOptions['open_tcp_keepalive'] = true;
        $serverOptions['tcp_keepidle'] = 60;
        $serverOptions['tcp_keepinterval'] = 10;
        $serverOptions['tcp_keepcount'] = 5;

        // Enable port reuse for multi-instance
        if ($this->performanceConfig['enable_reuse_port'] ?? false) {
            $serverOptions['enable_reuse_port'] = true;
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

    /**
     * Reload all workers gracefully
     */
    public function reload(): bool
    {
        return $this->server->reload();
    }

    /**
     * Stop the server
     */
    public function stop(): void
    {
        $this->server->stop();
    }

    /**
     * Get server statistics
     */
    public function stats(): array
    {
        return $this->server->stats();
    }
}
