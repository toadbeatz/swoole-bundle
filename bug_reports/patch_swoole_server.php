<?php
/**
 * Patch script to fix Swoole 6.1 API incompatibilities
 * BUG: Runtime::enableCoroutine() signature changed in Swoole 6.1
 */

$file = "/app/vendor/toadbeatz/swoole-bundle/src/Server/SwooleServer.php";
$content = file_get_contents($file);

// Fix: Swoole 6.1 enableCoroutine only takes 1 argument (hook_flags)
$content = str_replace(
    'Runtime::enableCoroutine($hookFlags, true);',
    'Runtime::enableCoroutine($hookFlags);',
    $content
);

if (file_put_contents($file, $content)) {
    echo "SwooleServer patched!\n";
} else {
    echo "Failed to patch!\n";
    exit(1);
}

