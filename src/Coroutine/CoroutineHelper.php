<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Coroutine;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;

/**
 * Advanced Helper class for working with Swoole 6.1 Coroutines
 * Exploits all coroutine capabilities for maximum performance
 * 
 * @since Swoole 6.1
 */
class CoroutineHelper
{
    /**
     * Run multiple operations concurrently using coroutines
     *
     * @param array $callbacks Array of callables to execute concurrently
     * @param bool $throwOnError Whether to throw on first error
     * @return array Results in the same order as callbacks
     */
    public static function parallel(array $callbacks, bool $throwOnError = true): array
    {
        $results = [];
        $errors = [];
        $ch = new Channel(\count($callbacks));

        foreach ($callbacks as $index => $callback) {
            Coroutine::create(function () use ($callback, $index, $ch) {
                try {
                    $result = $callback();
                    $ch->push(['index' => $index, 'result' => $result, 'error' => null]);
                } catch (\Throwable $e) {
                    $ch->push(['index' => $index, 'result' => null, 'error' => $e]);
                }
            });
        }

        // Collect results
        $collected = [];
        for ($i = 0; $i < \count($callbacks); $i++) {
            $collected[] = $ch->pop();
        }

        // Sort by index and extract results
        \usort($collected, fn($a, $b) => $a['index'] <=> $b['index']);
        
        foreach ($collected as $item) {
            if ($item['error']) {
                if ($throwOnError) {
                    throw $item['error'];
                }
                $errors[$item['index']] = $item['error'];
                $results[] = null;
            } else {
                $results[] = $item['result'];
            }
        }

        return $results;
    }

    /**
     * Run operations with WaitGroup for better synchronization
     */
    public static function parallelWait(array $callbacks): array
    {
        $results = [];
        $wg = new WaitGroup();

        foreach ($callbacks as $index => $callback) {
            $wg->add();
            Coroutine::create(function () use ($callback, $index, &$results, $wg) {
                try {
                    $results[$index] = $callback();
                } catch (\Throwable $e) {
                    $results[$index] = $e;
                } finally {
                    $wg->done();
                }
            });
        }

        $wg->wait();
        \ksort($results);
        
        return $results;
    }

    /**
     * Sleep in a non-blocking way using coroutines
     */
    public static function sleep(float $seconds): void
    {
        Coroutine::sleep($seconds);
    }

    /**
     * Execute a callback with timeout
     */
    public static function withTimeout(callable $callback, float $timeout): mixed
    {
        $ch = new Channel(1);

        $mainCid = Coroutine::create(function () use ($callback, $ch) {
            try {
                $result = $callback();
                $ch->push(['result' => $result, 'error' => null]);
            } catch (\Throwable $e) {
                $ch->push(['result' => null, 'error' => $e]);
            }
        });

        $timeoutCid = Coroutine::create(function () use ($timeout, $ch) {
            Coroutine::sleep($timeout);
            $ch->push(['result' => null, 'error' => new \RuntimeException('Operation timed out')]);
        });

        $data = $ch->pop();
        
        // Cancel the other coroutine
        if (Coroutine::exists($timeoutCid)) {
            Coroutine::cancel($timeoutCid);
        }
        if (Coroutine::exists($mainCid)) {
            Coroutine::cancel($mainCid);
        }

        if ($data['error']) {
            throw $data['error'];
        }

        return $data['result'];
    }

    /**
     * Cancel a coroutine (Swoole 6.1 feature)
     */
    public static function cancel(int $cid, bool $throwException = true): bool
    {
        return Coroutine::cancel($cid, $throwException);
    }

    /**
     * Check if coroutine is cancelled
     */
    public static function isCancelled(): bool
    {
        return Coroutine::isCanceled();
    }

    /**
     * Get current coroutine ID
     */
    public static function getCid(): int
    {
        return Coroutine::getCid();
    }

    /**
     * Get parent coroutine ID
     */
    public static function getPcid(): int
    {
        return Coroutine::getPcid();
    }

    /**
     * Check if in coroutine context
     */
    public static function inCoroutine(): bool
    {
        return Coroutine::getCid() > 0;
    }

    /**
     * Yield current coroutine
     */
    public static function yield(): void
    {
        Coroutine::yield();
    }

    /**
     * Resume a yielded coroutine
     */
    public static function resume(int $cid): bool
    {
        return Coroutine::resume($cid);
    }

    /**
     * Get coroutine statistics
     */
    public static function getStats(): array
    {
        return Coroutine::stats();
    }

    /**
     * Get list of all coroutine IDs
     */
    public static function listCoroutines(): array
    {
        return Coroutine::listCoroutines();
    }

    /**
     * Get coroutine count
     */
    public static function count(): int
    {
        return Coroutine::stats()['coroutine_num'] ?? 0;
    }

    /**
     * Get peak coroutine count
     */
    public static function peak(): int
    {
        return Coroutine::stats()['coroutine_peak_num'] ?? 0;
    }

    /**
     * Set coroutine options
     */
    public static function setOptions(array $options): void
    {
        Coroutine::set($options);
    }

    /**
     * Create a new coroutine and return its ID
     */
    public static function create(callable $callback): int
    {
        return Coroutine::create($callback);
    }

    /**
     * Defer execution until coroutine exits
     */
    public static function defer(callable $callback): void
    {
        Coroutine::defer($callback);
    }

    /**
     * Run multiple operations and return first successful result
     */
    public static function race(array $callbacks, float $timeout = 30.0): mixed
    {
        $ch = new Channel(1);
        $cids = [];

        foreach ($callbacks as $callback) {
            $cids[] = Coroutine::create(function () use ($callback, $ch) {
                try {
                    $result = $callback();
                    $ch->push(['result' => $result, 'error' => null]);
                } catch (\Throwable $e) {
                    $ch->push(['result' => null, 'error' => $e]);
                }
            });
        }

        $data = $ch->pop($timeout);

        // Cancel remaining coroutines
        foreach ($cids as $cid) {
            if (Coroutine::exists($cid)) {
                Coroutine::cancel($cid);
            }
        }

        if ($data === false) {
            throw new \RuntimeException('Race timed out');
        }

        if ($data['error']) {
            throw $data['error'];
        }

        return $data['result'];
    }

    /**
     * Retry operation with exponential backoff
     */
    public static function retry(
        callable $callback,
        int $maxAttempts = 3,
        float $initialDelay = 0.1,
        float $maxDelay = 5.0,
        float $multiplier = 2.0
    ): mixed {
        $attempt = 0;
        $delay = $initialDelay;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastError = $e;
                $attempt++;
                
                if ($attempt >= $maxAttempts) {
                    break;
                }

                self::sleep(\min($delay, $maxDelay));
                $delay *= $multiplier;
            }
        }

        throw $lastError ?? new \RuntimeException('Retry failed');
    }

    /**
     * Execute with circuit breaker pattern
     */
    public static function withCircuitBreaker(
        callable $callback,
        string $name,
        int $failureThreshold = 5,
        float $recoveryTimeout = 30.0
    ): mixed {
        static $circuits = [];

        if (!isset($circuits[$name])) {
            $circuits[$name] = [
                'failures' => 0,
                'last_failure' => 0,
                'state' => 'closed', // closed, open, half-open
            ];
        }

        $circuit = &$circuits[$name];

        // Check if circuit is open
        if ($circuit['state'] === 'open') {
            if (\time() - $circuit['last_failure'] >= $recoveryTimeout) {
                $circuit['state'] = 'half-open';
            } else {
                throw new \RuntimeException("Circuit breaker '{$name}' is open");
            }
        }

        try {
            $result = $callback();
            
            // Reset on success
            if ($circuit['state'] === 'half-open') {
                $circuit['state'] = 'closed';
            }
            $circuit['failures'] = 0;
            
            return $result;
        } catch (\Throwable $e) {
            $circuit['failures']++;
            $circuit['last_failure'] = \time();
            
            if ($circuit['failures'] >= $failureThreshold) {
                $circuit['state'] = 'open';
            }
            
            throw $e;
        }
    }
}

