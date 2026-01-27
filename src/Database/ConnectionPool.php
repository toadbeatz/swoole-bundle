<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Database;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine\MySQL;
use Swoole\Atomic;

/**
 * Connection Pool for Doctrine/PDO using Swoole Coroutine MySQL
 * Provides high-performance, non-blocking database connections
 */
class ConnectionPool
{
    private Channel $pool;
    private array $config;
    private int $size;
    private Atomic $currentSize; // Thread-safe counter
    private float $timeout;

    public function __construct(
        array $config,
        int $size = 10,
        float $timeout = 5.0
    ) {
        $this->config = $config;
        $this->size = $size;
        $this->timeout = $timeout;
        $this->pool = new Channel($size);
        $this->currentSize = new Atomic(0); // Thread-safe initialization
    }

    /**
     * Get a connection from the pool (non-blocking with coroutines)
     */
    public function get(): ?MySQL
    {
        $connection = $this->pool->pop($this->timeout);
        
        if ($connection === false) {
            // Pool is empty, try to create a new connection if under limit
            if ($this->currentSize->get() < $this->size) {
                return $this->createConnection();
            }
            
            // Wait for a connection to become available
            return $this->pool->pop($this->timeout) ?: null;
        }

        // Check if connection is still alive
        if (!$this->isConnectionAlive($connection)) {
            $this->currentSize->sub(1);
            return $this->createConnection();
        }

        return $connection;
    }

    /**
     * Return a connection to the pool
     */
    public function put(?MySQL $connection): void
    {
        if ($connection === null) {
            return;
        }

        if (!$this->isConnectionAlive($connection)) {
            $this->currentSize->sub(1);
            return;
        }

        // Try to put back in pool (non-blocking)
        if (!$this->pool->push($connection, 0.1)) {
            // Pool is full, close the connection
            $connection->close();
            $this->currentSize->sub(1);
        }
    }

    /**
     * Create a new MySQL connection using Swoole Coroutine
     */
    private function createConnection(): ?MySQL
    {
        // Thread-safe check and increment
        $current = $this->currentSize->get();
        if ($current >= $this->size) {
            return null;
        }
        
        // Atomic increment to prevent race condition
        if ($this->currentSize->add(1) > $this->size) {
            // Rollback if we exceeded the limit
            $this->currentSize->sub(1);
            return null;
        }

        $connection = new MySQL();
        
        $connected = $connection->connect([
            'host' => $this->config['host'] ?? '127.0.0.1',
            'port' => $this->config['port'] ?? 3306,
            'user' => $this->config['user'] ?? 'root',
            'password' => $this->config['password'] ?? '',
            'database' => $this->config['database'] ?? '',
            'charset' => $this->config['charset'] ?? 'utf8mb4',
            'timeout' => $this->config['timeout'] ?? 3.0,
            'connect_timeout' => $this->config['connect_timeout'] ?? 3.0,
            'read_timeout' => $this->config['read_timeout'] ?? 3.0,
            'strict_type' => $this->config['strict_type'] ?? false,
        ]);

        if (!$connected) {
            // Rollback on connection failure
            $this->currentSize->sub(1);
            return null;
        }

        return $connection;
    }

    /**
     * Check if connection is still alive
     */
    private function isConnectionAlive(MySQL $connection): bool
    {
        try {
            return $connection->connected && $connection->query('SELECT 1') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Close all connections in the pool
     */
    public function closeAll(): void
    {
        while (!$this->pool->isEmpty()) {
            $connection = $this->pool->pop(0.1);
            if ($connection) {
                $connection->close();
            }
        }
        $this->currentSize->set(0);
    }

    /**
     * Get current pool statistics
     */
    public function getStats(): array
    {
        $current = $this->currentSize->get();
        return [
            'size' => $this->size,
            'current' => $current,
            'available' => $this->pool->length(),
            'in_use' => $current - $this->pool->length(),
        ];
    }
}

