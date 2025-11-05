<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Lock;

use Swoole\Lock;

/**
 * Swoole Lock wrapper for cross-worker synchronization
 * Provides mutex, read-write locks, and semaphores
 */
class SwooleLock
{
    private Lock $lock;
    private int $type;

    public const TYPE_MUTEX = Lock::MUTEX;
    public const TYPE_RWLOCK = Lock::RWLOCK;
    public const TYPE_SPINLOCK = Lock::SPINLOCK;
    public const TYPE_SEM = Lock::SEM;

    public function __construct(int $type = self::TYPE_MUTEX)
    {
        $this->type = $type;
        $this->lock = new Lock($type);
    }

    /**
     * Acquire lock (blocking)
     */
    public function lock(): bool
    {
        return $this->lock->lock();
    }

    /**
     * Try to acquire lock (non-blocking)
     */
    public function trylock(): bool
    {
        return $this->lock->trylock();
    }

    /**
     * Release lock
     */
    public function unlock(): bool
    {
        return $this->lock->unlock();
    }

    /**
     * Lock with automatic release (RAII pattern)
     */
    public function synchronized(callable $callback): mixed
    {
        if (!$this->lock()) {
            throw new \RuntimeException('Failed to acquire lock');
        }

        try {
            return $callback();
        } finally {
            $this->unlock();
        }
    }

    /**
     * Get the underlying Swoole Lock instance
     */
    public function getLock(): Lock
    {
        return $this->lock;
    }

    /**
     * Get lock type
     */
    public function getType(): int
    {
        return $this->type;
    }
}

