<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Database;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine\PostgreSQL;
use Swoole\Atomic;

/**
 * PostgreSQL Connection Pool using Swoole Coroutine PostgreSQL
 * High-performance, non-blocking PostgreSQL connections
 * 
 * @since Swoole 6.1
 */
class PostgreSQLPool
{
    private Channel $pool;
    private array $config;
    private int $size;
    private Atomic $currentSize; // Thread-safe counter
    private float $timeout;
    private bool $initialized = false;

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
     * Initialize the pool with connections
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Pre-create minimum connections
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
     * Get a connection from the pool (non-blocking with coroutines)
     */
    public function get(): ?PostgreSQL
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $connection = $this->pool->pop($this->timeout);
        
        if ($connection === false) {
            if ($this->currentSize->get() < $this->size) {
                return $this->createConnection();
            }
            return $this->pool->pop($this->timeout) ?: null;
        }

        if (!$this->isConnectionAlive($connection)) {
            $this->currentSize->sub(1);
            return $this->createConnection();
        }

        return $connection;
    }

    /**
     * Return a connection to the pool
     */
    public function put(?PostgreSQL $connection): void
    {
        if ($connection === null) {
            return;
        }

        if (!$this->isConnectionAlive($connection)) {
            $this->currentSize->sub(1);
            return;
        }

        if (!$this->pool->push($connection, 0.1)) {
            $this->currentSize->sub(1);
        }
    }

    /**
     * Execute query with automatic connection management
     */
    public function query(string $sql, array $params = []): array|false
    {
        $connection = $this->get();
        
        if ($connection === null) {
            throw new \RuntimeException('Failed to get PostgreSQL connection from pool');
        }

        try {
            if (!empty($params)) {
                $stmt = $connection->prepare($sql);
                if ($stmt === false) {
                    throw new \RuntimeException('Failed to prepare statement: ' . $connection->error);
                }
                $result = $stmt->execute($params);
            } else {
                $result = $connection->query($sql);
            }

            if ($result === false) {
                throw new \RuntimeException('Query failed: ' . $connection->error);
            }

            return $connection->fetchAll($result) ?: [];
        } finally {
            $this->put($connection);
        }
    }

    /**
     * Execute statement (INSERT, UPDATE, DELETE)
     */
    public function execute(string $sql, array $params = []): int
    {
        $connection = $this->get();
        
        if ($connection === null) {
            throw new \RuntimeException('Failed to get PostgreSQL connection from pool');
        }

        try {
            if (!empty($params)) {
                $stmt = $connection->prepare($sql);
                if ($stmt === false) {
                    throw new \RuntimeException('Failed to prepare statement: ' . $connection->error);
                }
                $result = $stmt->execute($params);
            } else {
                $result = $connection->query($sql);
            }

            if ($result === false) {
                throw new \RuntimeException('Execute failed: ' . $connection->error);
            }

            return $connection->affectedRows();
        } finally {
            $this->put($connection);
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): PostgreSQL
    {
        $connection = $this->get();
        
        if ($connection === null) {
            throw new \RuntimeException('Failed to get PostgreSQL connection from pool');
        }

        $connection->query('BEGIN');
        return $connection;
    }

    /**
     * Commit transaction
     */
    public function commit(PostgreSQL $connection): void
    {
        try {
            $connection->query('COMMIT');
        } finally {
            $this->put($connection);
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback(PostgreSQL $connection): void
    {
        try {
            $connection->query('ROLLBACK');
        } finally {
            $this->put($connection);
        }
    }

    /**
     * Create a new PostgreSQL connection
     */
    private function createConnection(): ?PostgreSQL
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

        $connection = new PostgreSQL();
        
        $connectionString = $this->buildConnectionString();
        $connected = $connection->connect($connectionString);

        if (!$connected) {
            // Rollback on connection failure
            $this->currentSize->sub(1);
            return null;
        }

        return $connection;
    }

    /**
     * Build PostgreSQL connection string
     */
    private function buildConnectionString(): string
    {
        $parts = [];
        
        if (isset($this->config['host'])) {
            $parts[] = 'host=' . $this->config['host'];
        }
        if (isset($this->config['port'])) {
            $parts[] = 'port=' . $this->config['port'];
        }
        if (isset($this->config['database']) || isset($this->config['dbname'])) {
            $parts[] = 'dbname=' . ($this->config['database'] ?? $this->config['dbname']);
        }
        if (isset($this->config['user']) || isset($this->config['username'])) {
            $parts[] = 'user=' . ($this->config['user'] ?? $this->config['username']);
        }
        if (isset($this->config['password'])) {
            $parts[] = 'password=' . $this->config['password'];
        }
        if (isset($this->config['connect_timeout'])) {
            $parts[] = 'connect_timeout=' . $this->config['connect_timeout'];
        }

        return \implode(' ', $parts);
    }

    /**
     * Check if connection is still alive
     */
    private function isConnectionAlive(PostgreSQL $connection): bool
    {
        try {
            $result = $connection->query('SELECT 1');
            return $result !== false;
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
            // PostgreSQL connections are closed when object is destroyed
        }
        $this->currentSize->set(0);
        $this->initialized = false;
    }

    /**
     * Get current pool statistics
     */
    public function getStats(): array
    {
        $current = $this->currentSize->get();
        return [
            'driver' => 'postgresql',
            'size' => $this->size,
            'current' => $current,
            'available' => $this->pool->length(),
            'in_use' => $current - $this->pool->length(),
            'initialized' => $this->initialized,
        ];
    }
}







