<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\HotReload;

use Swoole\Timer;

/**
 * Hot Reload Watcher for development
 */
class HotReloadWatcher
{
    private array $watchPaths;
    private bool $enabled;
    private array $fileMtimes = [];
    private int $timerId;

    public function __construct(array $watchPaths, bool $enabled)
    {
        $this->watchPaths = $watchPaths;
        $this->enabled = $enabled;
    }

    public function start(callable $onChange): void
    {
        if (!$this->enabled) {
            return;
        }

        // Initialize file modification times
        $this->scanFiles();

        // Check for changes every 500ms
        $this->timerId = Timer::tick(500, function () use ($onChange) {
            if ($this->checkChanges()) {
                $onChange();
                $this->scanFiles(); // Rescan after change
            }
        });
    }

    public function stop(): void
    {
        if (isset($this->timerId)) {
            Timer::clear($this->timerId);
        }
    }

    private function scanFiles(): void
    {
        $this->fileMtimes = [];

        foreach ($this->watchPaths as $path) {
            if (!\is_dir($path) && !\is_file($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && \preg_match('/\.(php|yaml|yml|twig|js|css)$/', $file->getFilename())) {
                    $this->fileMtimes[$file->getRealPath()] = $file->getMTime();
                }
            }
        }
    }

    private function checkChanges(): bool
    {
        foreach ($this->fileMtimes as $file => $mtime) {
            if (!\file_exists($file)) {
                return true;
            }

            if (\filemtime($file) !== $mtime) {
                return true;
            }
        }

        // Check for new files
        $currentFiles = [];
        foreach ($this->watchPaths as $path) {
            if (!\is_dir($path) && !\is_file($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && \preg_match('/\.(php|yaml|yml|twig|js|css)$/', $file->getFilename())) {
                    $currentFiles[] = $file->getRealPath();
                }
            }
        }

        return \count($currentFiles) !== \count($this->fileMtimes);
    }
}

