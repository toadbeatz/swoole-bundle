<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Queue;

use Toadbeatz\SwooleBundle\Cache\SwooleTable;
use Toadbeatz\SwooleBundle\Atomic\SwooleAtomic;

/**
 * High-performance queue using Swoole Table
 * Provides FIFO queue operations with atomic access
 */
class SwooleQueue
{
    private SwooleTable $table;
    private $lock;
    private string $queueName;
    private SwooleAtomic $head;
    private SwooleAtomic $tail;
    private SwooleAtomic $sizeCounter;
    private int $maxSize;

    public function __construct(string $queueName, int $maxSize = 100000)
    {
        if (!\class_exists('Swoole\Lock')) {
            throw new \RuntimeException('Swoole\Lock class is not available. Please ensure the Swoole extension is installed and enabled.');
        }

        $this->queueName = $queueName;
        $this->maxSize = $maxSize;
        $lockClass = 'Swoole\Lock';
        // Use 1 directly (MUTEX) - avoids loading Swoole\Lock constants
        $this->lock = new $lockClass(1);
        
        // Create table for queue data
        $this->table = new SwooleTable($maxSize);
        $this->head = new \Toadbeatz\SwooleBundle\Atomic\SwooleAtomic(0);
        $this->tail = new \Toadbeatz\SwooleBundle\Atomic\SwooleAtomic(0);
        $this->sizeCounter = new \Toadbeatz\SwooleBundle\Atomic\SwooleAtomic(0);
    }

    /**
     * Push item to queue (non-blocking)
     */
    public function push(mixed $data): bool
    {
        if (!$this->lock->trylock()) {
            return false;
        }

        try {
            if ($this->getSize() >= $this->maxSize) {
                return false; // Queue is full
            }

            $tail = $this->tail->get();
            $key = 'item_' . $tail;
            $this->table->set($key, [
                'value' => \serialize($data),
                'index' => $tail,
                'timestamp' => \time(),
            ]);

            $this->tail->set(($tail + 1) % $this->maxSize);
            $this->sizeCounter->increment();
            return true;
        } finally {
            $this->lock->unlock();
        }
    }

    /**
     * Pop item from queue (non-blocking)
     */
    public function pop(): mixed
    {
        if (!$this->lock->trylock()) {
            return null;
        }

        try {
            if ($this->isEmpty()) {
                return null;
            }

            $head = $this->head->get();
            $key = 'item_' . $head;
            $item = $this->table->get($key);
            
            if ($item === null) {
                return null;
            }

            $this->table->del($key);
            $this->head->set(($head + 1) % $this->maxSize);
            $this->sizeCounter->decrement();

            return \unserialize($item['value']);
        } finally {
            $this->lock->unlock();
        }
    }

    /**
     * Peek at next item without removing it
     */
    public function peek(): mixed
    {
        if (!$this->lock->trylock()) {
            return null;
        }

        try {
            if ($this->isEmpty()) {
                return null;
            }

            $head = $this->head->get();
            $key = 'item_' . $head;
            $item = $this->table->get($key);
            
            return $item ? \unserialize($item['value']) : null;
        } finally {
            $this->lock->unlock();
        }
    }

    /**
     * Check if queue is empty
     */
    public function isEmpty(): bool
    {
        return $this->getSize() === 0;
    }

    /**
     * Get queue size
     */
    public function getSize(): int
    {
        return $this->sizeCounter->get();
    }

    /**
     * Clear queue
     */
    public function clear(): void
    {
        if (!$this->lock->lock()) {
            return;
        }

        try {
            for ($i = 0; $i < $this->maxSize; $i++) {
                $key = 'item_' . $i;
                $this->table->del($key);
            }
            $this->head->set(0);
            $this->tail->set(0);
            $this->sizeCounter->set(0);
        } finally {
            $this->lock->unlock();
        }
    }

    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        return [
            'name' => $this->queueName,
            'size' => $this->getSize(),
            'max_size' => $this->maxSize,
            'head' => $this->head->get(),
            'tail' => $this->tail->get(),
        ];
    }
}

