<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Debug;

/**
 * Debug Handler for Swoole environment
 * Provides support for dd(), var_dump(), etc.
 */
class DebugHandler
{
    private bool $enabled;
    private bool $enableDd;
    private bool $enableVarDump;
    private array $originalHandlers = [];

    public function __construct(bool $enabled, bool $enableDd, bool $enableVarDump)
    {
        $this->enabled = $enabled;
        $this->enableDd = $enableDd;
        $this->enableVarDump = $enableVarDump;
    }

    public function initialize(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Override dd() function if not exists or if we need to enhance it
        if ($this->enableDd && !\function_exists('dd')) {
            $this->overrideDd();
        }

        // Override var_dump if needed
        if ($this->enableVarDump) {
            $this->overrideVarDump();
        }
    }

    private function overrideDd(): void
    {
        if (!\function_exists('dd')) {
            \eval('
                function dd(...$vars) {
                    foreach ($vars as $v) {
                        \Symfony\Component\VarDumper\VarDumper::dump($v);
                    }
                    exit(1);
                }
            ');
        }
    }

    private function overrideVarDump(): void
    {
        // Store original var_dump handler
        if (\function_exists('xdebug_var_dump')) {
            $this->originalHandlers['var_dump'] = 'xdebug_var_dump';
        }

        // Ensure Symfony VarDumper works in Swoole context
        if (\class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            // VarDumper should work as-is, but we ensure output buffering is handled
            if (!\ob_get_level()) {
                \ob_start();
            }
        }
    }

    public function captureOutput(callable $callback): string
    {
        \ob_start();
        try {
            $callback();
            return \ob_get_clean();
        } catch (\Throwable $e) {
            \ob_end_clean();
            throw $e;
        }
    }
}

