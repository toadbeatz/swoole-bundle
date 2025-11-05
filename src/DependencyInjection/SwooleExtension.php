<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension for Swoole Bundle configuration
 */
class SwooleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Set configuration parameters
        $container->setParameter('swoole.http.host', $config['http']['host']);
        $container->setParameter('swoole.http.port', $config['http']['port']);
        $container->setParameter('swoole.http.options', $config['http']['options']);
        $container->setParameter('swoole.https.enabled', $config['https']['enabled']);
        $container->setParameter('swoole.https.port', $config['https']['port']);
        $container->setParameter('swoole.https.cert', $config['https']['cert']);
        $container->setParameter('swoole.https.key', $config['https']['key']);
        $container->setParameter('swoole.hot_reload.enabled', $config['hot_reload']['enabled']);
        $container->setParameter('swoole.hot_reload.watch', $config['hot_reload']['watch']);
        $container->setParameter('swoole.performance.worker_num', $config['performance']['worker_num'] ?? \swoole_cpu_num());
        $container->setParameter('swoole.performance.max_request', $config['performance']['max_request']);
        $container->setParameter('swoole.performance.enable_coroutine', $config['performance']['enable_coroutine']);
        $container->setParameter('swoole.performance.max_coroutine', $config['performance']['max_coroutine']);
        $container->setParameter('swoole.performance.coroutine_hook_flags', $config['performance']['coroutine_hook_flags'] ?? \SWOOLE_HOOK_ALL);
        $container->setParameter('swoole.debug.enabled', $config['debug']['enabled']);
        $container->setParameter('swoole.debug.enable_dd', $config['debug']['enable_dd']);
        $container->setParameter('swoole.debug.enable_var_dump', $config['debug']['enable_var_dump']);
        $container->setParameter('swoole.database.enable_pool', $config['database']['enable_pool'] ?? true);
        $container->setParameter('swoole.database.pool_size', $config['database']['pool_size'] ?? 10);
        $container->setParameter('swoole.database.pool_timeout', $config['database']['pool_timeout'] ?? 5.0);
        $container->setParameter('swoole.task.worker_num', $config['task']['worker_num'] ?? 2);
        $container->setParameter('swoole.task.max_request', $config['task']['max_request'] ?? 10000);
        $container->setParameter('swoole.scheduler.enabled', $config['scheduler']['enabled'] ?? true);
        $container->setParameter('swoole.rate_limiter.max_requests', $config['rate_limiter']['max_requests'] ?? 100);
        $container->setParameter('swoole.rate_limiter.window_seconds', $config['rate_limiter']['window_seconds'] ?? 60);
        $container->setParameter('swoole.metrics.enabled', $config['metrics']['enabled'] ?? true);
        $container->setParameter('swoole.metrics.export_interval', $config['metrics']['export_interval'] ?? 60);
        $container->setParameter('swoole.performance.task_worker_num', $config['task_worker_num'] ?? 2);
        $container->setParameter('swoole.performance.task_max_request', $config['task_max_request'] ?? 10000);
    }

    public function getAlias(): string
    {
        return 'swoole';
    }
}

