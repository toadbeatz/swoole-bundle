<?php
/**
 * Patch script to fix unsupported HTTP/2 options in Swoole 6.1
 * BUG: http2_* options are not supported in Swoole 6.1
 */

$file = "/app/vendor/toadbeatz/swoole-bundle/src/Server/SwooleServer.php";
$content = file_get_contents($file);

// Remove unsupported HTTP/2 options
$lines = [
    "            \$serverOptions['http2_header_table_size'] = \$this->http2Config['header_table_size'] ?? 4096;\n",
    "            \$serverOptions['http2_initial_window_size'] = \$this->http2Config['initial_window_size'] ?? 65535;\n",
    "            \$serverOptions['http2_max_concurrent_streams'] = \$this->http2Config['max_concurrent_streams'] ?? 128;\n",
    "            \$serverOptions['http2_max_frame_size'] = \$this->http2Config['max_frame_size'] ?? 16384;\n",
    "            \$serverOptions['http2_max_header_list_size'] = \$this->http2Config['max_header_list_size'] ?? 4096;\n",
];

foreach ($lines as $line) {
    $content = str_replace($line, "", $content);
}

// Add comment about Swoole 6.1 compatibility
$content = str_replace(
    "            \$serverOptions['open_http2_protocol'] = true;",
    "            \$serverOptions['open_http2_protocol'] = true;\n            // Note: HTTP/2 fine-tuning options removed for Swoole 6.1 compatibility",
    $content
);

if (file_put_contents($file, $content)) {
    echo "HTTP/2 options patched for Swoole 6.1!\n";
} else {
    echo "Failed to patch!\n";
    exit(1);
}

