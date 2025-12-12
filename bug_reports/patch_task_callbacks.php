<?php
/**
 * Patch script to add missing onTask and onFinish callbacks
 * BUG: Swoole requires onTask callback when task_worker_num > 0
 */

$file = "/app/vendor/toadbeatz/swoole-bundle/src/Server/HttpServerManager.php";
$content = file_get_contents($file);

// Check if already patched
if (strpos($content, 'onTask') !== false) {
    echo "HttpServerManager already patched!\n";
} else {
    // Add onTask and onFinish handlers in the initialize method
    $patch = <<<'PHP'

        // Handle task workers
        $server->on('task', function ($server, $taskId, $fromId, $data) {
            // Execute the task
            if (is_callable($data)) {
                return $data();
            }
            return $data;
        });

        // Handle task finish
        $server->on('finish', function ($server, $taskId, $data) {
            // Task completed
        });
PHP;

    // Insert after the 'shutdown' event handler
    $content = str_replace(
        "        // Shutdown event\n        \$server->on('shutdown', function () {\n            // Cleanup\n        });",
        "        // Shutdown event\n        \$server->on('shutdown', function () {\n            // Cleanup\n        });" . $patch,
        $content
    );
    
    if (file_put_contents($file, $content)) {
        echo "HttpServerManager patched with task callbacks!\n";
    } else {
        echo "Failed to patch HttpServerManager!\n";
        exit(1);
    }
}

