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

    // Lock type constants (matching Swoole\Lock constants)
    public const TYPE_MUTEX = 1;
    public const TYPE_RWLOCK = 2;
    public const TYPE_SPINLOCK = 3;
    public const TYPE_SEM = 4;

    public function __construct(int $type = self::TYPE_MUTEX)
    {
        if (!\extension_loaded('swoole') || !\class_exists('Swoole\Lock')) {
            throw new \RuntimeException('Swoole\Lock class is not available. Please ensure the Swoole extension is installed and enabled.');
        }

        $this->type = $type;
        $lockClass = 'Swoole\Lock';
        
        // Map our constants to Swoole constants if they exist
        $swooleType = $type;
        if (\defined('Swoole\Lock::MUTEX')) {
            $typeMap = [
                self::TYPE_MUTEX => \Swoole\Lock::MUTEX,
                self::TYPE_RWLOCK => \Swoole\Lock::RWLOCK,
                self::TYPE_SPINLOCK => \Swoole\Lock::SPINLOCK,
                self::TYPE_SEM => \Swoole\Lock::SEM,
            ];
            $swooleType = $typeMap[$type] ?? $type;
        }
        
        $this->lock = new $lockClass($swooleType);
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

