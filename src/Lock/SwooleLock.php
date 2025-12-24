<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Lock;

/**
 * Swoole Lock wrapper for cross-worker synchronization
 * Provides mutex, read-write locks, and semaphores
 */
class SwooleLock
{
    private $lock;
    private int $type;

    // Lock type constants (fallback if Swoole\Lock not available)
    public const TYPE_MUTEX = 1;
    public const TYPE_RWLOCK = 2;
    public const TYPE_SPINLOCK = 3;
    public const TYPE_SEM = 4;

    public function __construct(int $type = self::TYPE_MUTEX)
    {
        if (!\class_exists('Swoole\Lock')) {
            throw new \RuntimeException('Swoole\Lock class is not available. Please ensure the Swoole extension is installed and enabled.');
        }

        $this->type = $type;
        $lockClass = 'Swoole\Lock';
        
        // Use constants from Swoole\Lock if available
        if (\defined('Swoole\Lock::MUTEX')) {
            $this->type = $type;
        }
        
        $this->lock = new $lockClass($type);
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
    public function getLock()
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

