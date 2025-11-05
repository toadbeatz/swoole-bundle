<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Metrics;

use Toadbeatz\SwooleBundle\Atomic\SwooleAtomic;
use Swoole\Http\Server;

/**
 * Metrics Collector for Swoole Server
 * Collects and aggregates performance metrics
 */
class MetricsCollector
{
    private SwooleAtomic $requestCount;
    private SwooleAtomic $errorCount;
    private SwooleAtomic $responseTimeSum;
    private array $metrics = [];
    private Server $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->requestCount = new SwooleAtomic(0);
        $this->errorCount = new SwooleAtomic(0);
        $this->responseTimeSum = new SwooleAtomic(0);
    }

    /**
     * Record a request
     */
    public function recordRequest(float $responseTime, bool $isError = false): void
    {
        $this->requestCount->increment();
        
        if ($isError) {
            $this->errorCount->increment();
        }

        $this->responseTimeSum->add((int) ($responseTime * 1000)); // Convert to milliseconds
    }

    /**
     * Get server statistics
     */
    public function getServerStats(): array
    {
        $stats = $this->server->stats();
        
        return [
            'start_time' => $stats['start_time'] ?? 0,
            'connection_num' => $stats['connection_num'] ?? 0,
            'accept_count' => $stats['accept_count'] ?? 0,
            'close_count' => $stats['close_count'] ?? 0,
            'tasking_num' => $stats['tasking_num'] ?? 0,
            'request_count' => $stats['request_count'] ?? 0,
            'worker_num' => $stats['worker_num'] ?? 0,
            'idle_worker_num' => $stats['idle_worker_num'] ?? 0,
        ];
    }

    /**
     * Get application metrics
     */
    public function getMetrics(): array
    {
        $requestCount = $this->requestCount->get();
        $errorCount = $this->errorCount->get();
        $avgResponseTime = $requestCount > 0 
            ? ($this->responseTimeSum->get() / $requestCount) / 1000 
            : 0;

        return [
            'requests' => [
                'total' => $requestCount,
                'errors' => $errorCount,
                'success' => $requestCount - $errorCount,
                'error_rate' => $requestCount > 0 ? ($errorCount / $requestCount) * 100 : 0,
            ],
            'performance' => [
                'avg_response_time_ms' => \round($avgResponseTime, 2),
                'total_response_time_ms' => $this->responseTimeSum->get(),
            ],
            'server' => $this->getServerStats(),
        ];
    }

    /**
     * Reset metrics
     */
    public function reset(): void
    {
        $this->requestCount->set(0);
        $this->errorCount->set(0);
        $this->responseTimeSum->set(0);
    }

    /**
     * Get metrics in a format suitable for monitoring systems
     */
    public function export(): array
    {
        return [
            'timestamp' => \time(),
            'metrics' => $this->getMetrics(),
        ];
    }
}

