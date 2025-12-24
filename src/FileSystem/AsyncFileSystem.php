<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\FileSystem;

use Swoole\Coroutine;
use Swoole\Coroutine\System;

/**
 * Async File System operations using Swoole Coroutines
 * Non-blocking file I/O for high-performance applications
 * 
 * @since Swoole 6.1
 */
class AsyncFileSystem
{
    /**
     * Read file content asynchronously
     */
    public static function readFile(string $path): string|false
    {
        if (!self::isInCoroutine()) {
            return \file_get_contents($path);
        }

        return System::readFile($path);
    }

    /**
     * Write content to file asynchronously
     */
    public static function writeFile(string $path, string $content, int $flags = 0): int|false
    {
        if (!self::isInCoroutine()) {
            return \file_put_contents($path, $content, $flags);
        }

        return System::writeFile($path, $content, $flags);
    }

    /**
     * Append content to file asynchronously
     */
    public static function appendFile(string $path, string $content): int|false
    {
        return self::writeFile($path, $content, \FILE_APPEND);
    }

    /**
     * Check if file exists asynchronously
     */
    public static function exists(string $path): bool
    {
        if (!self::isInCoroutine()) {
            return \file_exists($path);
        }

        return System::statvfs($path) !== false || \file_exists($path);
    }

    /**
     * Get file stats asynchronously
     */
    public static function stat(string $path): array|false
    {
        if (!self::isInCoroutine()) {
            return \stat($path);
        }

        return System::lstat($path);
    }

    /**
     * Delete file asynchronously
     */
    public static function unlink(string $path): bool
    {
        if (!self::isInCoroutine()) {
            return \unlink($path);
        }

        return System::exec("rm -f " . \escapeshellarg($path))['code'] === 0;
    }

    /**
     * Create directory asynchronously
     */
    public static function mkdir(string $path, int $mode = 0755, bool $recursive = false): bool
    {
        if (!self::isInCoroutine()) {
            return \mkdir($path, $mode, $recursive);
        }

        $cmd = $recursive ? "mkdir -p " : "mkdir ";
        return System::exec($cmd . \escapeshellarg($path))['code'] === 0;
    }

    /**
     * Remove directory asynchronously
     */
    public static function rmdir(string $path, bool $recursive = false): bool
    {
        if (!self::isInCoroutine()) {
            if ($recursive) {
                return self::recursiveDelete($path);
            }
            return \rmdir($path);
        }

        $cmd = $recursive ? "rm -rf " : "rmdir ";
        return System::exec($cmd . \escapeshellarg($path))['code'] === 0;
    }

    /**
     * Copy file asynchronously
     */
    public static function copy(string $source, string $destination): bool
    {
        if (!self::isInCoroutine()) {
            return \copy($source, $destination);
        }

        $content = System::readFile($source);
        if ($content === false) {
            return false;
        }

        return System::writeFile($destination, $content) !== false;
    }

    /**
     * Move/rename file asynchronously
     */
    public static function rename(string $oldName, string $newName): bool
    {
        if (!self::isInCoroutine()) {
            return \rename($oldName, $newName);
        }

        return System::exec("mv " . \escapeshellarg($oldName) . " " . \escapeshellarg($newName))['code'] === 0;
    }

    /**
     * Get file size asynchronously
     */
    public static function fileSize(string $path): int|false
    {
        $stat = self::stat($path);
        return $stat ? ($stat['size'] ?? false) : false;
    }

    /**
     * Read file line by line asynchronously (generator)
     */
    public static function readLines(string $path): \Generator
    {
        $content = self::readFile($path);
        if ($content === false) {
            return;
        }

        $lines = \explode("\n", $content);
        foreach ($lines as $line) {
            yield $line;
        }
    }

    /**
     * Read file as JSON
     */
    public static function readJson(string $path): mixed
    {
        $content = self::readFile($path);
        if ($content === false) {
            return null;
        }

        return \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * Write JSON to file
     */
    public static function writeJson(string $path, mixed $data, int $flags = \JSON_PRETTY_PRINT): int|false
    {
        $content = \json_encode($data, $flags | \JSON_THROW_ON_ERROR);
        return self::writeFile($path, $content);
    }

    /**
     * Execute shell command asynchronously
     */
    public static function exec(string $command, bool $getOutput = true): array
    {
        if (!self::isInCoroutine()) {
            \exec($command, $output, $returnCode);
            return [
                'code' => $returnCode,
                'output' => $getOutput ? \implode("\n", $output) : '',
                'signal' => 0,
            ];
        }

        return System::exec($command, $getOutput);
    }

    /**
     * Wait for file change (inotify-like)
     */
    public static function waitFileChange(string $path, float $timeout = -1): bool
    {
        if (!self::isInCoroutine()) {
            return false;
        }

        $initialStat = self::stat($path);
        if ($initialStat === false) {
            return false;
        }

        $startTime = \microtime(true);
        while (true) {
            Coroutine::sleep(0.1);
            
            $currentStat = self::stat($path);
            if ($currentStat === false || $currentStat['mtime'] !== $initialStat['mtime']) {
                return true;
            }

            if ($timeout > 0 && (\microtime(true) - $startTime) >= $timeout) {
                return false;
            }
        }
    }

    /**
     * Recursive delete directory
     */
    private static function recursiveDelete(string $path): bool
    {
        if (\is_dir($path)) {
            $files = \scandir($path);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    self::recursiveDelete($path . \DIRECTORY_SEPARATOR . $file);
                }
            }
            return \rmdir($path);
        }
        
        return \unlink($path);
    }

    /**
     * Check if currently in coroutine context
     */
    private static function isInCoroutine(): bool
    {
        return Coroutine::getCid() > 0;
    }
}







