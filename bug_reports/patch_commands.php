<?php
/**
 * Patch script to fix Symfony 7+ Command compatibility
 * BUG: Commands need #[AsCommand] attribute instead of static properties
 */

$commandsDir = "/app/vendor/toadbeatz/swoole-bundle/src/Command";

// Patch ServerStopCommand
$file = "$commandsDir/ServerStopCommand.php";
$content = file_get_contents($file);

if (strpos($content, 'AsCommand') === false) {
    $content = str_replace(
        "use Symfony\\Component\\Console\\Command\\Command;",
        "use Symfony\\Component\\Console\\Attribute\\AsCommand;\nuse Symfony\\Component\\Console\\Command\\Command;",
        $content
    );
    
    $content = str_replace(
        "class ServerStopCommand extends Command\n{",
        "#[AsCommand(\n    name: 'swoole:server:stop',\n    description: 'Stop the Swoole HTTP server'\n)]\nclass ServerStopCommand extends Command\n{",
        $content
    );
    
    // Remove old properties
    $content = preg_replace("/\s+protected static \\\$defaultName[^;]+;/", "", $content);
    $content = preg_replace("/\s+protected static \\\$defaultDescription[^;]+;/", "", $content);
    
    file_put_contents($file, $content);
    echo "ServerStopCommand patched!\n";
}

// Patch ServerStartCommand
$file = "$commandsDir/ServerStartCommand.php";
$content = file_get_contents($file);

if (strpos($content, 'AsCommand') === false) {
    $content = str_replace(
        "use Symfony\\Component\\Console\\Command\\Command;",
        "use Symfony\\Component\\Console\\Attribute\\AsCommand;\nuse Symfony\\Component\\Console\\Command\\Command;",
        $content
    );
    
    $content = str_replace(
        "class ServerStartCommand extends Command\n{",
        "#[AsCommand(\n    name: 'swoole:server:start',\n    description: 'Start the Swoole HTTP server'\n)]\nclass ServerStartCommand extends Command\n{",
        $content
    );
    
    // Remove old properties
    $content = preg_replace("/\s+protected static \\\$defaultName[^;]+;/", "", $content);
    $content = preg_replace("/\s+protected static \\\$defaultDescription[^;]+;/", "", $content);
    
    file_put_contents($file, $content);
    echo "ServerStartCommand patched!\n";
}

// Patch HotReloadCommand
$file = "$commandsDir/HotReloadCommand.php";
$content = file_get_contents($file);

if (strpos($content, 'AsCommand') === false) {
    $content = str_replace(
        "use Symfony\\Component\\Console\\Command\\Command;",
        "use Symfony\\Component\\Console\\Attribute\\AsCommand;\nuse Symfony\\Component\\Console\\Command\\Command;",
        $content
    );
    
    $content = str_replace(
        "class HotReloadCommand extends Command\n{",
        "#[AsCommand(\n    name: 'swoole:server:watch',\n    description: 'Start Swoole server with hot reload'\n)]\nclass HotReloadCommand extends Command\n{",
        $content
    );
    
    // Remove old properties
    $content = preg_replace("/\s+protected static \\\$defaultName[^;]+;/", "", $content);
    $content = preg_replace("/\s+protected static \\\$defaultDescription[^;]+;/", "", $content);
    
    file_put_contents($file, $content);
    echo "HotReloadCommand patched!\n";
}

echo "All commands patched!\n";

