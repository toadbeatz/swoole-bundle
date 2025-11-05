<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Coroutine;

use Swoole\Coroutine;

/**
 * Helper class for working with Swoole Coroutines
 */
class CoroutineHelper
{
    /**
     * Run multiple operations concurrently using coroutines
     *
     * @param array $callbacks Array of callables to execute concurrently
     * @return array Results in the same order as callbacks
     */
    public static function parallel(array $callbacks): array
    {
        $results = [];
        $ch = new Coroutine\Channel(\count($callbacks));

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
                throw $item['error'];
            }
            $results[] = $item['result'];
        }

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
        $ch = new Coroutine\Channel(1);
        $result = null;
        $error = null;

        Coroutine::create(function () use ($callback, $ch) {
            try {
                $result = $callback();
                $ch->push(['result' => $result, 'error' => null]);
            } catch (\Throwable $e) {
                $ch->push(['result' => null, 'error' => $e]);
            }
        });

        Coroutine::create(function () use ($timeout, $ch) {
            Coroutine::sleep($timeout);
            $ch->push(['result' => null, 'error' => new \RuntimeException('Operation timed out')]);
        });

        $data = $ch->pop();
        if ($data['error']) {
            throw $data['error'];
        }

        return $data['result'];
    }
}

