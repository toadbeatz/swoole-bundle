<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Session;

use Toadbeatz\SwooleBundle\Cache\SwooleTable;
use SessionHandlerInterface;

/**
 * Swoole-based Session Handler for Symfony
 * High-performance session storage using Swoole Table
 */
class SwooleSessionHandler implements SessionHandlerInterface
{
    private SwooleTable $table;
    private int $ttl;

    public function __construct(SwooleTable $table, int $ttl = 3600)
    {
        $this->table = $table;
        $this->ttl = $ttl;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $key = 'session_' . $id;
        if ($this->table->exists($key)) {
            $data = $this->table->get($key);
            if ($data && isset($data['expires']) && $data['expires'] > \time()) {
                return $data['value'] ?? '';
            }
            // Expired, delete it
            $this->table->del($key);
        }
        return '';
    }

    public function write(string $id, string $data): bool
    {
        $key = 'session_' . $id;
        return $this->table->set($key, [
            'value' => $data,
            'expires' => \time() + $this->ttl,
            'created' => \time(),
        ]);
    }

    public function destroy(string $id): bool
    {
        return $this->table->del('session_' . $id);
    }

    public function gc(int $max_lifetime): int|false
    {
        // Garbage collection is handled automatically by checking expires
        // This is a no-op for Swoole Table
        return 0;
    }
}

