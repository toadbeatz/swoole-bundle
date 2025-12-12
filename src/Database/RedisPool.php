<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Database;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Redis;

/**
 * Redis Connection Pool using Swoole Coroutine Redis
 * High-performance, non-blocking Redis connections
 * 
 * @since Swoole 6.1
 */
class RedisPool
{
    private Channel $pool;
    private array $config;
    private int $size;
    private int $currentSize = 0;
    private float $timeout;
    private bool $initialized = false;

    public function __construct(
        array $config,
        int $size = 20,
        float $timeout = 3.0
    ) {
        $this->config = $config;
        $this->size = $size;
        $this->timeout = $timeout;
        $this->pool = new Channel($size);
    }

    /**
     * Initialize the pool with connections
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $minConnections = (int) \ceil($this->size / 2);
        for ($i = 0; $i < $minConnections; $i++) {
            $connection = $this->createConnection();
            if ($connection !== null) {
                $this->pool->push($connection);
            }
        }

        $this->initialized = true;
    }

    /**
     * Get a connection from the pool
     */
    public function get(): ?Redis
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $connection = $this->pool->pop($this->timeout);
        
        if ($connection === false) {
            if ($this->currentSize < $this->size) {
                return $this->createConnection();
            }
            return $this->pool->pop($this->timeout) ?: null;
        }

        if (!$this->isConnectionAlive($connection)) {
            $this->currentSize--;
            return $this->createConnection();
        }

        return $connection;
    }

    /**
     * Return a connection to the pool
     */
    public function put(?Redis $connection): void
    {
        if ($connection === null) {
            return;
        }

        if (!$this->isConnectionAlive($connection)) {
            $connection->close();
            $this->currentSize--;
            return;
        }

        if (!$this->pool->push($connection, 0.1)) {
            $connection->close();
            $this->currentSize--;
        }
    }

    /**
     * Execute Redis command with automatic connection management
     */
    public function command(string $command, ...$args): mixed
    {
        $connection = $this->get();
        
        if ($connection === null) {
            throw new \RuntimeException('Failed to get Redis connection from pool');
        }

        try {
            return $connection->$command(...$args);
        } finally {
            $this->put($connection);
        }
    }

    /**
     * GET operation
     */
    public function get_value(string $key): mixed
    {
        return $this->command('get', $key);
    }

    /**
     * SET operation
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($ttl !== null) {
            return $this->command('setex', $key, $ttl, $value);
        }
        return $this->command('set', $key, $value);
    }

    /**
     * DELETE operation
     */
    public function delete(string ...$keys): int
    {
        return $this->command('del', ...$keys);
    }

    /**
     * EXISTS operation
     */
    public function exists(string $key): bool
    {
        return (bool) $this->command('exists', $key);
    }

    /**
     * INCR operation
     */
    public function incr(string $key): int
    {
        return $this->command('incr', $key);
    }

    /**
     * DECR operation
     */
    public function decr(string $key): int
    {
        return $this->command('decr', $key);
    }

    /**
     * HGET operation
     */
    public function hget(string $key, string $field): mixed
    {
        return $this->command('hget', $key, $field);
    }

    /**
     * HSET operation
     */
    public function hset(string $key, string $field, mixed $value): int
    {
        return $this->command('hset', $key, $field, $value);
    }

    /**
     * HGETALL operation
     */
    public function hgetall(string $key): array
    {
        return $this->command('hgetall', $key) ?: [];
    }

    /**
     * LPUSH operation
     */
    public function lpush(string $key, mixed ...$values): int
    {
        return $this->command('lpush', $key, ...$values);
    }

    /**
     * RPOP operation
     */
    public function rpop(string $key): mixed
    {
        return $this->command('rpop', $key);
    }

    /**
     * EXPIRE operation
     */
    public function expire(string $key, int $seconds): bool
    {
        return (bool) $this->command('expire', $key, $seconds);
    }

    /**
     * TTL operation
     */
    public function ttl(string $key): int
    {
        return $this->command('ttl', $key);
    }

    /**
     * Pipeline execution for multiple commands
     */
    public function pipeline(callable $callback): array
    {
        $connection = $this->get();
        
        if ($connection === null) {
            throw new \RuntimeException('Failed to get Redis connection from pool');
        }

        try {
            $commands = [];
            $collector = new class($commands) {
                private array $commands;
                
                public function __construct(array &$commands) {
                    $this->commands = &$commands;
                }
                
                public function __call(string $method, array $args): self {
                    $this->commands[] = [$method, $args];
                    return $this;
                }
            };
            
            $callback($collector);
            
            // Execute pipeline
            $results = [];
            foreach ($commands as [$method, $args]) {
                $results[] = $connection->$method(...$args);
            }
            
            return $results;
        } finally {
            $this->put($connection);
        }
    }

    /**
     * Create a new Redis connection
     */
    private function createConnection(): ?Redis
    {
        if ($this->currentSize >= $this->size) {
            return null;
        }

        $connection = new Redis();
        
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 6379;
        $timeout = $this->config['timeout'] ?? 3.0;

        $connected = $connection->connect($host, $port, $timeout);

        if (!$connected) {
            return null;
        }

        // Authentication
        if (!empty($this->config['password'])) {
            if (!empty($this->config['username'])) {
                $connection->auth([$this->config['username'], $this->config['password']]);
            } else {
                $connection->auth($this->config['password']);
            }
        }

        // Select database
        if (isset($this->config['database'])) {
            $connection->select($this->config['database']);
        }

        $this->currentSize++;
        return $connection;
    }

    /**
     * Check if connection is still alive
     */
    private function isConnectionAlive(Redis $connection): bool
    {
        try {
            return $connection->ping() === '+PONG' || $connection->ping() === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Close all connections
     */
    public function closeAll(): void
    {
        while (!$this->pool->isEmpty()) {
            $connection = $this->pool->pop(0.1);
            if ($connection instanceof Redis) {
                $connection->close();
            }
        }
        $this->currentSize = 0;
        $this->initialized = false;
    }

    /**
     * Get pool statistics
     */
    public function getStats(): array
    {
        return [
            'driver' => 'redis',
            'size' => $this->size,
            'current' => $this->currentSize,
            'available' => $this->pool->length(),
            'in_use' => $this->currentSize - $this->pool->length(),
            'initialized' => $this->initialized,
        ];
    }
}


