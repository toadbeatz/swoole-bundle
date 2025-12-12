<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension for Swoole Bundle configuration
 * Compatible with Symfony 7.0 and 8.0
 * 
 * @since Swoole 6.1
 * @compatible Symfony 7.0, 8.0
 */
class SwooleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // HTTP Configuration
        $container->setParameter('swoole.http.host', $config['http']['host']);
        $container->setParameter('swoole.http.port', $config['http']['port']);
        $container->setParameter('swoole.http.options', $config['http']['options']);

        // HTTPS Configuration
        $container->setParameter('swoole.https.enabled', $config['https']['enabled']);
        $container->setParameter('swoole.https.port', $config['https']['port']);
        $container->setParameter('swoole.https.cert', $config['https']['cert']);
        $container->setParameter('swoole.https.key', $config['https']['key']);
        $container->setParameter('swoole.https.ca', $config['https']['ca']);
        $container->setParameter('swoole.https.verify_peer', $config['https']['verify_peer']);
        $container->setParameter('swoole.https.protocols', $config['https']['protocols']);

        // HTTP/2 Configuration
        $container->setParameter('swoole.http2.header_table_size', $config['http2']['header_table_size']);
        $container->setParameter('swoole.http2.initial_window_size', $config['http2']['initial_window_size']);
        $container->setParameter('swoole.http2.max_concurrent_streams', $config['http2']['max_concurrent_streams']);
        $container->setParameter('swoole.http2.max_frame_size', $config['http2']['max_frame_size']);
        $container->setParameter('swoole.http2.max_header_list_size', $config['http2']['max_header_list_size']);

        // Hot Reload Configuration
        $container->setParameter('swoole.hot_reload.enabled', $config['hot_reload']['enabled']);
        $container->setParameter('swoole.hot_reload.watch', $config['hot_reload']['watch']);
        $container->setParameter('swoole.hot_reload.interval', $config['hot_reload']['interval']);

        // Performance Configuration
        $container->setParameter('swoole.performance.worker_num', $config['performance']['worker_num'] ?? $this->detectCpuCount());
        $container->setParameter('swoole.performance.max_request', $config['performance']['max_request']);
        $container->setParameter('swoole.performance.enable_coroutine', $config['performance']['enable_coroutine']);
        $container->setParameter('swoole.performance.max_coroutine', $config['performance']['max_coroutine']);
        $container->setParameter('swoole.performance.coroutine_hook_flags', $config['performance']['coroutine_hook_flags'] ?? $this->getDefaultHookFlags());
        $container->setParameter('swoole.performance.max_connection', $config['performance']['max_connection']);
        $container->setParameter('swoole.performance.buffer_output_size', $config['performance']['buffer_output_size']);
        $container->setParameter('swoole.performance.socket_buffer_size', $config['performance']['socket_buffer_size']);
        $container->setParameter('swoole.performance.package_max_length', $config['performance']['package_max_length']);
        $container->setParameter('swoole.performance.enable_compression', $config['performance']['enable_compression']);
        $container->setParameter('swoole.performance.compression_level', $config['performance']['compression_level']);
        $container->setParameter('swoole.performance.compression_min_length', $config['performance']['compression_min_length']);
        $container->setParameter('swoole.performance.websocket_compression', $config['performance']['websocket_compression']);
        $container->setParameter('swoole.performance.daemonize', $config['performance']['daemonize']);
        $container->setParameter('swoole.performance.heartbeat_interval', $config['performance']['heartbeat_interval']);
        $container->setParameter('swoole.performance.heartbeat_idle_time', $config['performance']['heartbeat_idle_time']);
        $container->setParameter('swoole.performance.enable_reuse_port', $config['performance']['enable_reuse_port']);
        $container->setParameter('swoole.performance.thread_mode', $config['performance']['thread_mode']);
        $container->setParameter('swoole.performance.base_mode', $config['performance']['base_mode']);

        // Debug Configuration
        $container->setParameter('swoole.debug.enabled', $config['debug']['enabled']);
        $container->setParameter('swoole.debug.enable_dd', $config['debug']['enable_dd']);
        $container->setParameter('swoole.debug.enable_var_dump', $config['debug']['enable_var_dump']);

        // Database Configuration
        $container->setParameter('swoole.database.enable_pool', $config['database']['enable_pool']);
        $container->setParameter('swoole.database.mysql.pool_size', $config['database']['mysql']['pool_size']);
        $container->setParameter('swoole.database.mysql.timeout', $config['database']['mysql']['timeout']);
        $container->setParameter('swoole.database.postgresql.pool_size', $config['database']['postgresql']['pool_size']);
        $container->setParameter('swoole.database.postgresql.timeout', $config['database']['postgresql']['timeout']);
        $container->setParameter('swoole.database.redis.pool_size', $config['database']['redis']['pool_size']);
        $container->setParameter('swoole.database.redis.timeout', $config['database']['redis']['timeout']);

        // Task Configuration
        $container->setParameter('swoole.task.worker_num', $config['task']['worker_num']);
        $container->setParameter('swoole.task.max_request', $config['task']['max_request']);

        // Scheduler Configuration
        $container->setParameter('swoole.scheduler.enabled', $config['scheduler']['enabled']);

        // Rate Limiter Configuration
        $container->setParameter('swoole.rate_limiter.enabled', $config['rate_limiter']['enabled']);
        $container->setParameter('swoole.rate_limiter.max_requests', $config['rate_limiter']['max_requests']);
        $container->setParameter('swoole.rate_limiter.window_seconds', $config['rate_limiter']['window_seconds']);

        // Metrics Configuration
        $container->setParameter('swoole.metrics.enabled', $config['metrics']['enabled']);
        $container->setParameter('swoole.metrics.export_interval', $config['metrics']['export_interval']);

        // Thread Pool Configuration
        $container->setParameter('swoole.thread_pool.enabled', $config['thread_pool']['enabled']);
        $container->setParameter('swoole.thread_pool.size', $config['thread_pool']['size']);

        // Process Pool Configuration
        $container->setParameter('swoole.process_pool.enabled', $config['process_pool']['enabled']);
        $container->setParameter('swoole.process_pool.size', $config['process_pool']['size']);

        // WebSocket Configuration
        $container->setParameter('swoole.websocket.enabled', $config['websocket']['enabled']);
        $container->setParameter('swoole.websocket.max_frame_size', $config['websocket']['max_frame_size']);
        $container->setParameter('swoole.websocket.compression', $config['websocket']['compression']);
    }

    public function getAlias(): string
    {
        return 'swoole';
    }

    /**
     * Detect CPU count for default worker number
     */
    private function detectCpuCount(): int
    {
        if (\function_exists('swoole_cpu_num')) {
            return \swoole_cpu_num();
        }

        if (\PHP_OS_FAMILY === 'Linux') {
            $cpuInfo = @\file_get_contents('/proc/cpuinfo');
            if ($cpuInfo) {
                \preg_match_all('/^processor/m', $cpuInfo, $matches);
                return \count($matches[0]) ?: 4;
            }
        }

        return 4; // Default fallback
    }

    /**
     * Get default coroutine hook flags
     */
    private function getDefaultHookFlags(): int
    {
        if (\defined('SWOOLE_HOOK_ALL')) {
            return \SWOOLE_HOOK_ALL;
        }

        return 0;
    }
}
