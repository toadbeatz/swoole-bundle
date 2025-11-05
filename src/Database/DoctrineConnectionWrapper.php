<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Swoole\Coroutine\MySQL;

/**
 * Doctrine Connection Wrapper using Swoole Connection Pool
 * Provides high-performance database operations with connection pooling
 */
class DoctrineConnectionWrapper
{
    private ConnectionPool $pool;
    private Connection $doctrineConnection;

    public function __construct(ConnectionPool $pool, Connection $doctrineConnection)
    {
        $this->pool = $pool;
        $this->doctrineConnection = $doctrineConnection;
    }

    /**
     * Execute a query using the connection pool
     */
    public function executeQuery(string $sql, array $params = [], array $types = []): Result
    {
        $swooleConnection = $this->pool->get();
        
        if ($swooleConnection === null) {
            // Fallback to Doctrine standard connection if pool fails
            return $this->doctrineConnection->executeQuery($sql, $params, $types);
        }

        try {
            // Use Swoole connection for better performance
            $prepared = $this->prepareQuery($swooleConnection, $sql, $params);
            $result = $swooleConnection->query($prepared['sql'], $prepared['params']);
            
            if ($result === false) {
                throw new \RuntimeException('Query execution failed: ' . $swooleConnection->error);
            }

            return new SwooleResult($result);
        } finally {
            $this->pool->put($swooleConnection);
        }
    }

    /**
     * Execute a statement using the connection pool
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        $swooleConnection = $this->pool->get();
        
        if ($swooleConnection === null) {
            return $this->doctrineConnection->executeStatement($sql, $params, $types);
        }

        try {
            $prepared = $this->prepareQuery($swooleConnection, $sql, $params);
            $result = $swooleConnection->query($prepared['sql'], $prepared['params']);
            
            if ($result === false) {
                throw new \RuntimeException('Statement execution failed: ' . $swooleConnection->error);
            }

            return $swooleConnection->affected_rows;
        } finally {
            $this->pool->put($swooleConnection);
        }
    }

    /**
     * Prepare query with parameter binding
     */
    private function prepareQuery(MySQL $connection, string $sql, array $params): array
    {
        if (empty($params)) {
            return ['sql' => $sql, 'params' => []];
        }

        // Simple parameter replacement for Swoole MySQL
        // For production, consider using prepared statements
        $preparedParams = [];
        foreach ($params as $key => $value) {
            if (\is_string($key) && \str_starts_with($key, ':')) {
                $placeholder = $key;
            } elseif (\is_int($key)) {
                $placeholder = '?';
            } else {
                $placeholder = ':' . $key;
            }

            $preparedParams[] = $this->escapeValue($connection, $value);
            
            // Replace placeholder in SQL
            $sql = \str_replace($placeholder, $this->escapeValue($connection, $value), $sql);
        }

        return ['sql' => $sql, 'params' => $preparedParams];
    }

    /**
     * Escape value for SQL
     */
    private function escapeValue(MySQL $connection, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        // For strings, use MySQL escape
        return "'" . \addslashes((string) $value) . "'";
    }

    /**
     * Get the underlying Doctrine connection
     */
    public function getDoctrineConnection(): Connection
    {
        return $this->doctrineConnection;
    }

    /**
     * Get pool statistics
     */
    public function getPoolStats(): array
    {
        return $this->pool->getStats();
    }
}

/**
 * Simple Result wrapper for Swoole MySQL results
 */
class SwooleResult implements Result
{
    private array $data;
    private int $currentIndex = 0;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function fetchNumeric(): array|false
    {
        if (!isset($this->data[$this->currentIndex])) {
            return false;
        }

        $row = $this->data[$this->currentIndex];
        $this->currentIndex++;

        return \array_values($row);
    }

    public function fetchAssociative(): array|false
    {
        if (!isset($this->data[$this->currentIndex])) {
            return false;
        }

        $row = $this->data[$this->currentIndex];
        $this->currentIndex++;

        return $row;
    }

    public function fetchOne(): mixed
    {
        $row = $this->fetchNumeric();
        return $row ? $row[0] : false;
    }

    public function fetchAllNumeric(): array
    {
        return \array_map(fn($row) => \array_values($row), $this->data);
    }

    public function fetchAllAssociative(): array
    {
        return $this->data;
    }

    public function fetchFirstColumn(): array
    {
        return \array_map(fn($row) => \array_values($row)[0] ?? null, $this->data);
    }

    public function rowCount(): int
    {
        return \count($this->data);
    }

    public function columnCount(): int
    {
        return empty($this->data) ? 0 : \count($this->data[0]);
    }

    public function free(): void
    {
        $this->data = [];
        $this->currentIndex = 0;
    }
}

