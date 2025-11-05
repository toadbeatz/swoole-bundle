<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Task;

use Swoole\Http\Server;
use Swoole\Server\Task;

/**
 * Task Worker for handling async tasks in Swoole
 * Allows offloading heavy tasks to dedicated task workers
 */
class TaskWorker
{
    private Server $server;
    private array $taskHandlers = [];

    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->initialize();
    }

    /**
     * Initialize task workers
     */
    private function initialize(): void
    {
        $this->server->on('task', function (Server $server, Task $task) {
            $this->handleTask($server, $task);
        });

        $this->server->on('finish', function (Server $server, int $taskId, $data) {
            $this->handleFinish($server, $taskId, $data);
        });
    }

    /**
     * Register a task handler
     */
    public function registerHandler(string $type, callable $handler): void
    {
        $this->taskHandlers[$type] = $handler;
    }

    /**
     * Dispatch a task asynchronously
     */
    public function dispatch(TaskData $taskData): int
    {
        return $this->server->task(\serialize($taskData));
    }

    /**
     * Dispatch a task and wait for result (blocking)
     */
    public function dispatchSync(TaskData $taskData, float $timeout = 5.0): mixed
    {
        $taskId = $this->server->taskwait(\serialize($taskData), $timeout);
        
        if ($taskId === false) {
            throw new \RuntimeException('Task execution timed out');
        }

        return \unserialize($taskId);
    }

    /**
     * Handle incoming task
     */
    private function handleTask(Server $server, Task $task): void
    {
        try {
            $taskData = \unserialize($task->data);
            
            if (!$taskData instanceof TaskData) {
                $server->finish(['error' => 'Invalid task data']);
                return;
            }

            $handler = $this->taskHandlers[$taskData->getType()] ?? null;
            
            if ($handler === null) {
                $server->finish(['error' => "No handler registered for type: {$taskData->getType()}"]);
                return;
            }

            $result = $handler($taskData->getData());
            $server->finish(\serialize($result));
        } catch (\Throwable $e) {
            $server->finish(\serialize([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));
        }
    }

    /**
     * Handle task finish
     */
    private function handleFinish(Server $server, int $taskId, $data): void
    {
        // Can be extended to handle task completion callbacks
    }
}

/**
 * Task Data structure
 */
class TaskData
{
    public function __construct(
        private string $type,
        private mixed $data
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}

