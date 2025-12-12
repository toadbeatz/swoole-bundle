<?php
/**
 * Patch script to fix missing service configurations
 * BUG: HotReloadWatcher missing arguments configuration
 */

$file = "/app/vendor/toadbeatz/swoole-bundle/src/Resources/config/services.yaml";
$content = file_get_contents($file);

// Check if already patched
if (strpos($content, 'HotReloadWatcher:') !== false) {
    echo "Already patched!\n";
    exit(0);
}

// Add HotReloadWatcher configuration before aliases section
$patch = <<<'YAML'

    # Hot Reload Watcher
    Toadbeatz\SwooleBundle\HotReload\HotReloadWatcher:
        arguments:
            $watchPaths: '%swoole.hot_reload.watch%'
            $enabled: '%swoole.hot_reload.enabled%'

    # Coroutine Helper
    Toadbeatz\SwooleBundle\Coroutine\CoroutineHelper: ~

    # Async File System
    Toadbeatz\SwooleBundle\FileSystem\AsyncFileSystem: ~

    # Task Worker
    Toadbeatz\SwooleBundle\Task\TaskWorker: ~
YAML;

// Insert before aliases
$content = str_replace(
    "    # ============================================\n    # ALIASES FOR SYMFONY INTEGRATION",
    $patch . "\n\n    # ============================================\n    # ALIASES FOR SYMFONY INTEGRATION",
    $content
);

if (file_put_contents($file, $content)) {
    echo "services.yaml patched!\n";
} else {
    echo "Failed to patch!\n";
    exit(1);
}

