<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\RateLimiter;

use Toadbeatz\SwooleBundle\Cache\SwooleTable;

/**
 * Rate Limiter using Swoole Table
 * Provides token bucket algorithm for rate limiting
 */
class RateLimiter
{
    private SwooleTable $table;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(int $maxRequests = 100, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->table = new SwooleTable(100000); // 100K unique keys
    }

    /**
     * Check if request is allowed
     */
    public function isAllowed(string $identifier): bool
    {
        $key = 'rate_limit_' . $identifier;
        $now = \time();

        $data = $this->table->get($key);
        
        if ($data === null) {
            // First request
            $this->table->set($key, [
                'count' => 1,
                'window_start' => $now,
                'expires' => $now + $this->windowSeconds,
            ]);
            return true;
        }

        // Check if window expired
        if ($now >= $data['expires']) {
            // Reset window
            $this->table->set($key, [
                'count' => 1,
                'window_start' => $now,
                'expires' => $now + $this->windowSeconds,
            ]);
            return true;
        }

        // Check if limit exceeded
        if ($data['count'] >= $this->maxRequests) {
            return false;
        }

        // Increment counter
        $this->table->set($key, [
            'count' => $data['count'] + 1,
            'window_start' => $data['window_start'],
            'expires' => $data['expires'],
        ]);

        return true;
    }

    /**
     * Get remaining requests for identifier
     */
    public function getRemaining(string $identifier): int
    {
        $key = 'rate_limit_' . $identifier;
        $data = $this->table->get($key);
        
        if ($data === null) {
            return $this->maxRequests;
        }

        $now = \time();
        if ($now >= $data['expires']) {
            return $this->maxRequests;
        }

        return \max(0, $this->maxRequests - $data['count']);
    }

    /**
     * Reset rate limit for identifier
     */
    public function reset(string $identifier): void
    {
        $key = 'rate_limit_' . $identifier;
        $this->table->del($key);
    }

    /**
     * Get rate limit information
     */
    public function getInfo(string $identifier): array
    {
        $key = 'rate_limit_' . $identifier;
        $data = $this->table->get($key);
        
        if ($data === null) {
            return [
                'allowed' => true,
                'remaining' => $this->maxRequests,
                'reset_at' => \time() + $this->windowSeconds,
            ];
        }

        $now = \time();
        $remaining = $this->getRemaining($identifier);

        return [
            'allowed' => $remaining > 0,
            'remaining' => $remaining,
            'reset_at' => $data['expires'],
            'current_count' => $data['count'],
        ];
    }
}

