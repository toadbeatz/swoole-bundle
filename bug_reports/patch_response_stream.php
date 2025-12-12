<?php
/**
 * Patch script to fix SwooleResponseStream signature issues
 * BUG: current() returns wrong type
 */

$file = "/app/vendor/toadbeatz/swoole-bundle/src/HttpClient/SwooleResponseStream.php";

$content = <<<'PHP'
<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\HttpClient;

use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Swoole Response Stream implementation
 * Fixed to comply with Symfony HttpClient contracts
 */
class SwooleResponseStream implements ResponseStreamInterface
{
    /** @var \Iterator<ResponseInterface, ChunkInterface> */
    private \Iterator $iterator;
    
    public function __construct(iterable $responses)
    {
        if ($responses instanceof \Iterator) {
            $this->iterator = $responses;
        } elseif (is_array($responses)) {
            $this->iterator = new \ArrayIterator($responses);
        } else {
            $this->iterator = (function() use ($responses): \Generator {
                yield from $responses;
            })();
        }
    }

    public function key(): ResponseInterface
    {
        return $this->iterator->key();
    }

    public function current(): ChunkInterface
    {
        $current = $this->iterator->current();
        
        // If not already a ChunkInterface, wrap it
        if (!$current instanceof ChunkInterface) {
            return new SwooleChunk($current);
        }
        
        return $current;
    }

    public function next(): void
    {
        $this->iterator->next();
    }

    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
    }
}

PHP;

if (file_put_contents($file, $content)) {
    echo "SwooleResponseStream patched!\n";
} else {
    echo "Failed to patch SwooleResponseStream!\n";
    exit(1);
}

// Now create SwooleChunk if it doesn't exist
$chunkFile = "/app/vendor/toadbeatz/swoole-bundle/src/HttpClient/SwooleChunk.php";
if (!file_exists($chunkFile)) {
    $chunkContent = <<<'PHP'
<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\HttpClient;

use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * Swoole HTTP Client Chunk implementation
 */
class SwooleChunk implements ChunkInterface
{
    private string $content;
    private bool $isFirst;
    private bool $isLast;
    private int $offset;
    private ?string $error;
    
    public function __construct(
        mixed $content = '',
        bool $isFirst = false,
        bool $isLast = false,
        int $offset = 0,
        ?string $error = null
    ) {
        $this->content = is_string($content) ? $content : '';
        $this->isFirst = $isFirst;
        $this->isLast = $isLast;
        $this->offset = $offset;
        $this->error = $error;
    }

    public function isTimeout(): bool
    {
        return false;
    }

    public function isFirst(): bool
    {
        return $this->isFirst;
    }

    public function isLast(): bool
    {
        return $this->isLast;
    }

    public function getInformationalStatus(): ?array
    {
        return null;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}

PHP;
    
    if (file_put_contents($chunkFile, $chunkContent)) {
        echo "SwooleChunk created!\n";
    } else {
        echo "Failed to create SwooleChunk!\n";
        exit(1);
    }
}

echo "Done!\n";

