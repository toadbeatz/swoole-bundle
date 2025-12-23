<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Task;

use Swoole\Timer;

/**
 * Scheduler for periodic tasks in Swoole
 * Provides cron-like functionality with Swoole Timers
 */
class Scheduler
{
    private array $scheduledTasks = [];
    private array $timerIds = [];

    /**
     * Schedule a task to run periodically
     */
    public function schedule(string $name, callable $callback, float $interval, bool $immediate = false): void
    {
        if (isset($this->scheduledTasks[$name])) {
            $this->unschedule($name);
        }

        $this->scheduledTasks[$name] = [
            'callback' => $callback,
            'interval' => $interval,
            'immediate' => $immediate,
        ];

        $this->startTask($name);
    }

    /**
     * Schedule a task to run once after a delay
     */
    public function scheduleOnce(string $name, callable $callback, float $delay): void
    {
        $timerId = Timer::after((int) ($delay * 1000), function () use ($name, $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                // Log error but don't break scheduler
                \error_log("Scheduled task '{$name}' failed: " . $e->getMessage());
            }
            unset($this->timerIds[$name]);
        });

        $this->timerIds[$name] = $timerId;
    }

    /**
     * Unschedule a task
     */
    public function unschedule(string $name): void
    {
        if (isset($this->timerIds[$name])) {
            Timer::clear($this->timerIds[$name]);
            unset($this->timerIds[$name]);
        }

        unset($this->scheduledTasks[$name]);
    }

    /**
     * Start a scheduled task
     */
    private function startTask(string $name): void
    {
        if (!isset($this->scheduledTasks[$name])) {
            return;
        }

        $task = $this->scheduledTasks[$name];
        
        if ($task['immediate']) {
            // Run immediately first
            try {
                $task['callback']();
            } catch (\Throwable $e) {
                \error_log("Scheduled task '{$name}' failed: " . $e->getMessage());
            }
        }

        // Schedule periodic execution
        $timerId = Timer::tick((int) ($task['interval'] * 1000), function () use ($name, $task) {
            try {
                $task['callback']();
            } catch (\Throwable $e) {
                \error_log("Scheduled task '{$name}' failed: " . $e->getMessage());
            }
        });

        $this->timerIds[$name] = $timerId;
    }

    /**
     * Clear all scheduled tasks
     */
    public function clearAll(): void
    {
        foreach ($this->timerIds as $timerId) {
            Timer::clear($timerId);
        }

        $this->timerIds = [];
        $this->scheduledTasks = [];
    }

    /**
     * Alias for clearAll() for compatibility
     */
    public function clear(): void
    {
        $this->clearAll();
    }

    /**
     * Get list of scheduled tasks
     */
    public function getScheduledTasks(): array
    {
        return \array_keys($this->scheduledTasks);
    }
}

