<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Cache;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Swoole Table-based Cache Adapter for Symfony
 * High-performance in-memory cache using Swoole Table
 */
class SwooleCacheAdapter implements CacheInterface
{
    private SwooleTable $table;
    private string $prefix;

    public function __construct(SwooleTable $table, string $prefix = 'cache_')
    {
        $this->table = $table;
        $this->prefix = $prefix;
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $fullKey = $this->prefix . $key;
        
        if ($this->table->exists($fullKey)) {
            $item = $this->table->get($fullKey);
            if ($item && isset($item['value']) && isset($item['expires'])) {
                if ($item['expires'] > \time()) {
                    return \unserialize($item['value']);
                }
                // Expired, remove it
                $this->table->del($fullKey);
            }
        }

        // Cache miss, call callback
        $item = new SwooleCacheItem($key);
        $value = $callback($item);
        
        // Store in cache with TTL from item
        $ttl = $item->getTtl();

        $this->table->set($fullKey, [
            'value' => \serialize($value),
            'expires' => \time() + $ttl,
            'created' => \time(),
        ]);

        return $value;
    }

    public function delete(string $key): bool
    {
        return $this->table->del($this->prefix . $key);
    }
}

