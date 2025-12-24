<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Thread;

use Swoole\Thread;
use Swoole\Thread\Queue;
use Swoole\Thread\Map;
use Swoole\Thread\ArrayList;

/**
 * Thread Pool for Swoole 6.1 Thread Mode
 * High-performance multi-threading for CPU-intensive tasks
 * 
 * @since Swoole 6.1
 * @requires SWOOLE_THREAD mode
 */
class ThreadPool
{
    private int $size;
    private array $threads = [];
    private ?Queue $taskQueue = null;
    private ?Queue $resultQueue = null;
    private ?Map $sharedData = null;
    private bool $running = false;

    public function __construct(int $size = 4)
    {
        $this->size = $size;
    }

    /**
     * Check if Thread mode is available
     */
    public static function isAvailable(): bool
    {
        return \defined('SWOOLE_THREAD') && \class_exists(Thread::class);
    }

    /**
     * Initialize the thread pool
     */
    public function initialize(): void
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('Swoole Thread mode is not available. Compile Swoole with --enable-swoole-thread');
        }

        if ($this->running) {
            return;
        }

        $this->taskQueue = new Queue();
        $this->resultQueue = new Queue();
        $this->sharedData = new Map();
        $this->running = true;

        // Start worker threads
        for ($i = 0; $i < $this->size; $i++) {
            $this->threads[$i] = $this->createWorkerThread($i);
        }
    }

    /**
     * Submit a task to the thread pool
     */
    public function submit(callable $task, mixed $data = null): string
    {
        if (!$this->running) {
            throw new \RuntimeException('Thread pool is not running');
        }

        $taskId = \uniqid('task_', true);
        
        $this->taskQueue->push([
            'id' => $taskId,
            'callable' => \serialize($task),
            'data' => $data,
            'submitted_at' => \microtime(true),
        ]);

        return $taskId;
    }

    /**
     * Get result for a task (blocking)
     */
    public function getResult(string $taskId, float $timeout = 30.0): mixed
    {
        $startTime = \microtime(true);
        
        while ((\microtime(true) - $startTime) < $timeout) {
            $result = $this->resultQueue->pop(0.1);
            
            if ($result !== false && isset($result['id']) && $result['id'] === $taskId) {
                if (isset($result['error'])) {
                    throw new \RuntimeException($result['error']);
                }
                return $result['result'] ?? null;
            }
            
            // Put back if not our result
            if ($result !== false) {
                $this->resultQueue->push($result);
            }
        }

        throw new \RuntimeException("Task {$taskId} timed out");
    }

    /**
     * Execute task and wait for result
     */
    public function execute(callable $task, mixed $data = null, float $timeout = 30.0): mixed
    {
        $taskId = $this->submit($task, $data);
        return $this->getResult($taskId, $timeout);
    }

    /**
     * Set shared data accessible by all threads
     */
    public function setShared(string $key, mixed $value): void
    {
        if ($this->sharedData === null) {
            throw new \RuntimeException('Thread pool not initialized');
        }
        $this->sharedData[$key] = $value;
    }

    /**
     * Get shared data
     */
    public function getShared(string $key): mixed
    {
        if ($this->sharedData === null) {
            return null;
        }
        return $this->sharedData[$key] ?? null;
    }

    /**
     * Shutdown the thread pool
     */
    public function shutdown(): void
    {
        if (!$this->running) {
            return;
        }

        $this->running = false;

        // Send shutdown signal to all workers
        for ($i = 0; $i < $this->size; $i++) {
            $this->taskQueue->push(['shutdown' => true]);
        }

        // Wait for threads to finish
        foreach ($this->threads as $thread) {
            if ($thread instanceof Thread) {
                $thread->join();
            }
        }

        $this->threads = [];
    }

    /**
     * Get pool statistics
     */
    public function getStats(): array
    {
        return [
            'size' => $this->size,
            'running' => $this->running,
            'pending_tasks' => $this->taskQueue?->count() ?? 0,
            'pending_results' => $this->resultQueue?->count() ?? 0,
            'active_threads' => \count($this->threads),
        ];
    }

    /**
     * Create a worker thread
     */
    private function createWorkerThread(int $workerId): Thread
    {
        return new Thread(function () use ($workerId) {
            while (true) {
                $task = $this->taskQueue->pop(-1); // Blocking pop
                
                if ($task === false) {
                    continue;
                }

                if (isset($task['shutdown'])) {
                    break;
                }

                try {
                    $callable = \unserialize($task['callable']);
                    $result = $callable($task['data'], $this->sharedData);
                    
                    $this->resultQueue->push([
                        'id' => $task['id'],
                        'result' => $result,
                        'worker_id' => $workerId,
                        'completed_at' => \microtime(true),
                    ]);
                } catch (\Throwable $e) {
                    $this->resultQueue->push([
                        'id' => $task['id'],
                        'error' => $e->getMessage(),
                        'worker_id' => $workerId,
                        'completed_at' => \microtime(true),
                    ]);
                }
            }
        });
    }

    /**
     * Get number of active threads
     */
    public static function activeCount(): int
    {
        if (!self::isAvailable()) {
            return 0;
        }
        return Thread::activeCount();
    }
}

/**
 * Thread-safe counter using Thread Map
 */
class ThreadSafeCounter
{
    private Map $data;
    private string $key;

    public function __construct(string $key = 'counter', int $initial = 0)
    {
        $this->data = new Map();
        $this->key = $key;
        $this->data[$key] = $initial;
    }

    public function increment(int $value = 1): int
    {
        $current = $this->data[$this->key] ?? 0;
        $this->data[$this->key] = $current + $value;
        return $this->data[$this->key];
    }

    public function decrement(int $value = 1): int
    {
        return $this->increment(-$value);
    }

    public function get(): int
    {
        return $this->data[$this->key] ?? 0;
    }

    public function set(int $value): void
    {
        $this->data[$this->key] = $value;
    }
}







