<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Cache;

use Swoole\Table;

/**
 * Wrapper for Swoole Table
 */
class SwooleTable
{
    private Table $table;
    private int $size;

    public function __construct(int $size = 1000000)
    {
        $this->size = $size;
        $this->table = new Table($size);
        $this->table->column('value', Table::TYPE_STRING, 1024 * 1024); // 1MB max value
        $this->table->column('expires', Table::TYPE_INT);
        $this->table->column('created', Table::TYPE_INT);
        $this->table->create();
    }

    public static function createCacheTable(): self
    {
        return new self(1000000); // 1M rows
    }

    public function set(string $key, array $value): bool
    {
        return $this->table->set($key, $value);
    }

    public function get(string $key): ?array
    {
        $result = $this->table->get($key);
        return $result ?: null;
    }

    public function exists(string $key): bool
    {
        return $this->table->exists($key);
    }

    public function del(string $key): bool
    {
        return $this->table->del($key);
    }

    public function getTable(): Table
    {
        return $this->table;
    }
}

