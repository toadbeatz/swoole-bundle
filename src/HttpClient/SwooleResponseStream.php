<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\HttpClient;

use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Swoole Response Stream implementation
 * 
 * Properly implements ResponseStreamInterface with correct
 * return types for Symfony HttpClient contracts.
 */
class SwooleResponseStream implements ResponseStreamInterface
{
    /** @var \Iterator<ResponseInterface, ChunkInterface> */
    private \Iterator $iterator;
    
    /** @var array<int, ResponseInterface> */
    private array $responses = [];
    
    private int $currentIndex = 0;

    /**
     * @param iterable<ResponseInterface> $responses
     */
    public function __construct(iterable $responses)
    {
        if ($responses instanceof \Iterator) {
            $this->iterator = $this->createIterator($responses);
        } elseif (\is_array($responses)) {
            $this->responses = \array_values($responses);
            $this->iterator = $this->createIteratorFromArray($this->responses);
        } else {
            $this->iterator = $this->createIterator($this->toIterator($responses));
        }
    }

    /**
     * @return ResponseInterface The current response being streamed
     */
    public function key(): ResponseInterface
    {
        $response = $this->responses[$this->currentIndex] ?? null;
        
        if ($response === null) {
            throw new \LogicException('No current response available.');
        }
        
        return $response;
    }

    /**
     * @return ChunkInterface The current chunk of the response
     */
    public function current(): ChunkInterface
    {
        $current = $this->iterator->current();
        
        // If already a ChunkInterface, return as-is
        if ($current instanceof ChunkInterface) {
            return $current;
        }
        
        // Wrap non-chunk data in a SwooleChunk
        if (\is_string($current)) {
            return new SwooleChunk($current);
        }
        
        // For responses or other types, create an appropriate chunk
        if ($current instanceof ResponseInterface) {
            try {
                $content = $current->getContent(false);
                return new SwooleChunk($content, true, true);
            } catch (\Throwable $e) {
                return SwooleChunk::createError($e->getMessage());
            }
        }
        
        return new SwooleChunk('');
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
        $this->currentIndex = 0;
        $this->iterator->rewind();
    }

    /**
     * @param \Iterator<mixed> $iterator
     * @return \Generator<ResponseInterface, ChunkInterface>
     */
    private function createIterator(\Iterator $iterator): \Generator
    {
        $index = 0;
        foreach ($iterator as $response) {
            if ($response instanceof ResponseInterface) {
                $this->responses[$index] = $response;
                $this->currentIndex = $index;
                
                try {
                    $content = $response->getContent(false);
                    yield $response => new SwooleChunk($content, true, true);
                } catch (\Throwable $e) {
                    yield $response => SwooleChunk::createError($e->getMessage());
                }
                
                $index++;
            }
        }
    }

    /**
     * @param array<ResponseInterface> $responses
     * @return \Generator<ResponseInterface, ChunkInterface>
     */
    private function createIteratorFromArray(array $responses): \Generator
    {
        foreach ($responses as $index => $response) {
            $this->currentIndex = $index;
            
            try {
                $content = $response->getContent(false);
                yield $response => new SwooleChunk($content, true, true);
            } catch (\Throwable $e) {
                yield $response => SwooleChunk::createError($e->getMessage());
            }
        }
    }

    /**
     * @param iterable<ResponseInterface> $iterable
     * @return \Iterator<ResponseInterface>
     */
    private function toIterator(iterable $iterable): \Iterator
    {
        return (function () use ($iterable): \Generator {
            yield from $iterable;
        })();
    }
}
