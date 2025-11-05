<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Atomic;

use Swoole\Atomic;

/**
 * Atomic operations wrapper for thread-safe counters
 * Provides atomic increment, decrement, and comparison operations
 */
class SwooleAtomic
{
    private Atomic $atomic;

    public function __construct(int $initialValue = 0)
    {
        $this->atomic = new Atomic($initialValue);
    }

    /**
     * Get current value
     */
    public function get(): int
    {
        return $this->atomic->get();
    }

    /**
     * Set value
     */
    public function set(int $value): void
    {
        $this->atomic->set($value);
    }

    /**
     * Add value (atomic)
     */
    public function add(int $value): int
    {
        return $this->atomic->add($value);
    }

    /**
     * Subtract value (atomic)
     */
    public function sub(int $value): int
    {
        return $this->atomic->sub($value);
    }

    /**
     * Increment by 1 (atomic)
     */
    public function increment(): int
    {
        return $this->atomic->add(1);
    }

    /**
     * Decrement by 1 (atomic)
     */
    public function decrement(): int
    {
        return $this->atomic->sub(1);
    }

    /**
     * Compare and swap (atomic)
     */
    public function compareAndSwap(int $cmpVal, int $newVal): bool
    {
        return $this->atomic->cmpset($cmpVal, $newVal);
    }

    /**
     * Wait until value becomes non-zero
     */
    public function wait(float $timeout = 1.0): bool
    {
        $start = \microtime(true);
        while ($this->get() === 0) {
            if (\microtime(true) - $start > $timeout) {
                return false;
            }
            \usleep(1000); // 1ms
        }
        return true;
    }

    /**
     * Wake up waiting coroutines/processes
     */
    public function wakeup(int $count = 1): void
    {
        $this->add($count);
    }
}

