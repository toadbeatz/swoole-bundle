<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Process;

use Swoole\Process;
use Swoole\Coroutine;

/**
 * Process Manager for Swoole
 * Manage child processes for parallel execution
 * 
 * @since Swoole 6.1
 */
class ProcessManager
{
    private array $processes = [];
    private array $processCallbacks = [];
    private bool $running = false;

    /**
     * Create a new child process
     */
    public function create(
        callable $callback,
        bool $redirectStdio = false,
        int $pipeType = \SWOOLE_PROCESS_PIPE_RDWR,
        bool $enableCoroutine = true
    ): int {
        $process = new Process(
            function (Process $worker) use ($callback) {
                try {
                    $callback($worker);
                } catch (\Throwable $e) {
                    \error_log("Process error: " . $e->getMessage());
                    $worker->exit(1);
                }
            },
            $redirectStdio,
            $pipeType,
            $enableCoroutine
        );

        $pid = $process->start();
        
        if ($pid === false) {
            throw new \RuntimeException('Failed to start process');
        }

        $this->processes[$pid] = $process;
        $this->processCallbacks[$pid] = $callback;

        return $pid;
    }

    /**
     * Create a daemon process
     */
    public function daemon(callable $callback, string $name = 'daemon'): int
    {
        Process::daemon();
        
        return $this->create(function (Process $worker) use ($callback, $name) {
            \cli_set_process_title("swoole: {$name}");
            $callback($worker);
        }, false, 0, true);
    }

    /**
     * Create multiple worker processes
     */
    public function createWorkers(int $count, callable $callback): array
    {
        $pids = [];
        
        for ($i = 0; $i < $count; $i++) {
            $pids[] = $this->create(function (Process $worker) use ($callback, $i) {
                $callback($worker, $i);
            });
        }

        return $pids;
    }

    /**
     * Send signal to process
     */
    public function signal(int $pid, int $signal = \SIGTERM): bool
    {
        return Process::kill($pid, $signal);
    }

    /**
     * Wait for process to exit
     */
    public function wait(int $pid = -1, bool $blocking = true): array|false
    {
        return Process::wait($blocking);
    }

    /**
     * Wait for all processes to exit
     */
    public function waitAll(): array
    {
        $results = [];
        
        while (\count($this->processes) > 0) {
            $result = Process::wait(true);
            if ($result !== false) {
                $results[$result['pid']] = $result;
                unset($this->processes[$result['pid']]);
                unset($this->processCallbacks[$result['pid']]);
            }
        }

        return $results;
    }

    /**
     * Kill all processes
     */
    public function killAll(int $signal = \SIGTERM): void
    {
        foreach (\array_keys($this->processes) as $pid) {
            $this->signal($pid, $signal);
        }
    }

    /**
     * Write to process stdin
     */
    public function write(int $pid, string $data): int|false
    {
        if (!isset($this->processes[$pid])) {
            return false;
        }

        return $this->processes[$pid]->write($data);
    }

    /**
     * Read from process stdout
     */
    public function read(int $pid, int $bufferSize = 8192): string|false
    {
        if (!isset($this->processes[$pid])) {
            return false;
        }

        return $this->processes[$pid]->read($bufferSize);
    }

    /**
     * Get process by PID
     */
    public function getProcess(int $pid): ?Process
    {
        return $this->processes[$pid] ?? null;
    }

    /**
     * Get all process PIDs
     */
    public function getPids(): array
    {
        return \array_keys($this->processes);
    }

    /**
     * Check if process is running
     */
    public function isRunning(int $pid): bool
    {
        return Process::kill($pid, 0);
    }

    /**
     * Set process affinity (CPU binding)
     */
    public function setAffinity(int $pid, array $cpuIds): bool
    {
        if (!\function_exists('swoole_set_process_affinity')) {
            return false;
        }

        return \swoole_set_process_affinity($pid, $cpuIds);
    }

    /**
     * Register signal handler
     */
    public static function registerSignal(int $signal, callable $callback): void
    {
        Process::signal($signal, $callback);
    }

    /**
     * Create an alarm timer
     */
    public static function alarm(int $intervalUsec, int $type = \ITIMER_REAL): bool
    {
        return Process::alarm($intervalUsec, $type);
    }

    /**
     * Get process statistics
     */
    public function getStats(): array
    {
        $stats = [
            'total' => \count($this->processes),
            'running' => 0,
            'processes' => [],
        ];

        foreach ($this->processes as $pid => $process) {
            $isRunning = $this->isRunning($pid);
            if ($isRunning) {
                $stats['running']++;
            }
            $stats['processes'][$pid] = [
                'running' => $isRunning,
                'pipe' => $process->pipe,
            ];
        }

        return $stats;
    }
}

/**
 * Pool of worker processes
 */
class WorkerPool
{
    private ProcessManager $manager;
    private int $workerCount;
    private array $workers = [];

    public function __construct(int $workerCount = 4)
    {
        $this->manager = new ProcessManager();
        $this->workerCount = $workerCount;
    }

    /**
     * Start worker pool
     */
    public function start(callable $workerCallback): void
    {
        $this->workers = $this->manager->createWorkers(
            $this->workerCount,
            $workerCallback
        );
    }

    /**
     * Stop all workers
     */
    public function stop(): void
    {
        $this->manager->killAll(\SIGTERM);
        $this->manager->waitAll();
        $this->workers = [];
    }

    /**
     * Restart all workers
     */
    public function restart(callable $workerCallback): void
    {
        $this->stop();
        $this->start($workerCallback);
    }

    /**
     * Get active worker count
     */
    public function getActiveCount(): int
    {
        $count = 0;
        foreach ($this->workers as $pid) {
            if ($this->manager->isRunning($pid)) {
                $count++;
            }
        }
        return $count;
    }
}


