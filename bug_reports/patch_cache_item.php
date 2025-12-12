<?php
/**
 * Patch script to fix SwooleCacheItem missing methods
 * This is a temporary fix - the bundle should be updated
 */

$file = "/app/vendor/toadbeatz/swoole-bundle/src/Cache/SwooleCacheItem.php";
$content = file_get_contents($file);

// Check if already patched
if (strpos($content, 'getMetadata') !== false) {
    echo "Already patched!\n";
    exit(0);
}

// Add missing methods before the last closing brace
$patch = <<<'PHP'

    private array $tags = [];
    
    /**
     * Tags the cache item (Symfony ItemInterface requirement)
     */
    public function tag(string|iterable $tags): static
    {
        if (is_string($tags)) {
            $tags = [$tags];
        }
        foreach ($tags as $tag) {
            $this->tags[$tag] = $tag;
        }
        return $this;
    }
    
    /**
     * Returns metadata about the cache item (Symfony ItemInterface requirement)
     */
    public function getMetadata(): array
    {
        return [
            'expiry' => $this->ttl ? time() + $this->ttl : null,
            'ctime' => time(),
            'tags' => array_values($this->tags),
        ];
    }
}

PHP;

// Replace the last closing brace with the patch
$content = preg_replace('/}\s*$/', $patch, $content);

if (file_put_contents($file, $content)) {
    echo "Patched successfully!\n";
} else {
    echo "Failed to patch!\n";
    exit(1);
}

