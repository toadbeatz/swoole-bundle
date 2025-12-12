<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Metrics;

use Toadbeatz\SwooleBundle\Atomic\SwooleAtomic;
use Toadbeatz\SwooleBundle\Server\SwooleServer;
use Swoole\Http\Server;

/**
 * Metrics Collector for Swoole Server
 * Collects and aggregates performance metrics
 * 
 * @since Swoole 6.1
 */
class MetricsCollector
{
    private SwooleAtomic $requestCount;
    private SwooleAtomic $errorCount;
    private SwooleAtomic $responseTimeSum;
    private SwooleAtomic $bytesIn;
    private SwooleAtomic $bytesOut;
    private array $metrics = [];
    private SwooleServer $swooleServer;

    public function __construct(SwooleServer $server)
    {
        $this->swooleServer = $server;
        $this->requestCount = new SwooleAtomic(0);
        $this->errorCount = new SwooleAtomic(0);
        $this->responseTimeSum = new SwooleAtomic(0);
        $this->bytesIn = new SwooleAtomic(0);
        $this->bytesOut = new SwooleAtomic(0);
    }

    /**
     * Get the underlying server
     */
    public function getServer(): Server
    {
        return $this->swooleServer->getServer();
    }

    /**
     * Record a request
     */
    public function recordRequest(float $responseTime, bool $isError = false, int $bytesReceived = 0, int $bytesSent = 0): void
    {
        $this->requestCount->increment();
        
        if ($isError) {
            $this->errorCount->increment();
        }

        $this->responseTimeSum->add((int) ($responseTime * 1000000)); // Convert to microseconds
        $this->bytesIn->add($bytesReceived);
        $this->bytesOut->add($bytesSent);
    }

    /**
     * Get server statistics
     */
    public function getServerStats(): array
    {
        $stats = $this->swooleServer->getServer()->stats();
        
        return [
            'start_time' => $stats['start_time'] ?? 0,
            'start_time_human' => isset($stats['start_time']) ? \date('Y-m-d H:i:s', $stats['start_time']) : null,
            'connection_num' => $stats['connection_num'] ?? 0,
            'accept_count' => $stats['accept_count'] ?? 0,
            'close_count' => $stats['close_count'] ?? 0,
            'tasking_num' => $stats['tasking_num'] ?? 0,
            'request_count' => $stats['request_count'] ?? 0,
            'worker_num' => $stats['worker_num'] ?? 0,
            'task_worker_num' => $stats['task_worker_num'] ?? 0,
            'idle_worker_num' => $stats['idle_worker_num'] ?? 0,
            'coroutine_num' => $stats['coroutine_num'] ?? 0,
            'dispatch_count' => $stats['dispatch_count'] ?? 0,
        ];
    }

    /**
     * Get application metrics
     */
    public function getMetrics(): array
    {
        $requestCount = $this->requestCount->get();
        $errorCount = $this->errorCount->get();
        $responseTimeSum = $this->responseTimeSum->get();
        
        $avgResponseTime = $requestCount > 0 
            ? ($responseTimeSum / $requestCount) / 1000 // Convert to milliseconds
            : 0;

        return [
            'requests' => [
                'total' => $requestCount,
                'errors' => $errorCount,
                'success' => $requestCount - $errorCount,
                'error_rate' => $requestCount > 0 ? \round(($errorCount / $requestCount) * 100, 2) : 0,
            ],
            'performance' => [
                'avg_response_time_ms' => \round($avgResponseTime, 3),
                'total_response_time_ms' => \round($responseTimeSum / 1000, 3),
                'requests_per_second' => $this->calculateRequestsPerSecond(),
            ],
            'network' => [
                'bytes_received' => $this->bytesIn->get(),
                'bytes_sent' => $this->bytesOut->get(),
                'bytes_received_human' => $this->formatBytes($this->bytesIn->get()),
                'bytes_sent_human' => $this->formatBytes($this->bytesOut->get()),
            ],
            'server' => $this->getServerStats(),
            'memory' => [
                'usage' => \memory_get_usage(true),
                'peak' => \memory_get_peak_usage(true),
                'usage_human' => $this->formatBytes(\memory_get_usage(true)),
                'peak_human' => $this->formatBytes(\memory_get_peak_usage(true)),
            ],
        ];
    }

    /**
     * Calculate requests per second
     */
    private function calculateRequestsPerSecond(): float
    {
        $stats = $this->swooleServer->getServer()->stats();
        $startTime = $stats['start_time'] ?? \time();
        $uptime = \time() - $startTime;
        
        if ($uptime <= 0) {
            return 0;
        }

        return \round($this->requestCount->get() / $uptime, 2);
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < \count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return \round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Reset metrics
     */
    public function reset(): void
    {
        $this->requestCount->set(0);
        $this->errorCount->set(0);
        $this->responseTimeSum->set(0);
        $this->bytesIn->set(0);
        $this->bytesOut->set(0);
    }

    /**
     * Get metrics in a format suitable for monitoring systems
     */
    public function export(): array
    {
        return [
            'timestamp' => \time(),
            'datetime' => \date('Y-m-d\TH:i:s\Z'),
            'metrics' => $this->getMetrics(),
        ];
    }

    /**
     * Export metrics in Prometheus format
     */
    public function exportPrometheus(): string
    {
        $metrics = $this->getMetrics();
        $output = [];

        // Request metrics
        $output[] = "# HELP swoole_requests_total Total number of requests";
        $output[] = "# TYPE swoole_requests_total counter";
        $output[] = "swoole_requests_total {$metrics['requests']['total']}";

        $output[] = "# HELP swoole_errors_total Total number of errors";
        $output[] = "# TYPE swoole_errors_total counter";
        $output[] = "swoole_errors_total {$metrics['requests']['errors']}";

        $output[] = "# HELP swoole_response_time_ms Average response time in milliseconds";
        $output[] = "# TYPE swoole_response_time_ms gauge";
        $output[] = "swoole_response_time_ms {$metrics['performance']['avg_response_time_ms']}";

        $output[] = "# HELP swoole_requests_per_second Requests per second";
        $output[] = "# TYPE swoole_requests_per_second gauge";
        $output[] = "swoole_requests_per_second {$metrics['performance']['requests_per_second']}";

        // Connection metrics
        $output[] = "# HELP swoole_connections Current number of connections";
        $output[] = "# TYPE swoole_connections gauge";
        $output[] = "swoole_connections {$metrics['server']['connection_num']}";

        // Memory metrics
        $output[] = "# HELP swoole_memory_usage_bytes Current memory usage";
        $output[] = "# TYPE swoole_memory_usage_bytes gauge";
        $output[] = "swoole_memory_usage_bytes {$metrics['memory']['usage']}";

        // Worker metrics
        $output[] = "# HELP swoole_workers Number of workers";
        $output[] = "# TYPE swoole_workers gauge";
        $output[] = "swoole_workers {$metrics['server']['worker_num']}";

        $output[] = "# HELP swoole_idle_workers Number of idle workers";
        $output[] = "# TYPE swoole_idle_workers gauge";
        $output[] = "swoole_idle_workers {$metrics['server']['idle_worker_num']}";

        return \implode("\n", $output) . "\n";
    }

    /**
     * Export metrics as JSON
     */
    public function exportJson(): string
    {
        return \json_encode($this->export(), \JSON_PRETTY_PRINT);
    }
}
