<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\HttpClient;

use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * Swoole HTTP Client Chunk implementation
 * 
 * Represents a chunk of data received from an HTTP response.
 * Used for streaming responses and chunked transfer encoding.
 */
class SwooleChunk implements ChunkInterface
{
    private string $content;
    private bool $isFirst;
    private bool $isLast;
    private int $offset;
    private ?string $error;
    private bool $isTimeout;
    private ?array $informationalStatus;

    public function __construct(
        mixed $content = '',
        bool $isFirst = false,
        bool $isLast = false,
        int $offset = 0,
        ?string $error = null,
        bool $isTimeout = false,
        ?array $informationalStatus = null
    ) {
        $this->content = \is_string($content) ? $content : (\is_scalar($content) ? (string) $content : '');
        $this->isFirst = $isFirst;
        $this->isLast = $isLast;
        $this->offset = $offset;
        $this->error = $error;
        $this->isTimeout = $isTimeout;
        $this->informationalStatus = $informationalStatus;
    }

    /**
     * Tells when the idle timeout has been reached.
     */
    public function isTimeout(): bool
    {
        return $this->isTimeout;
    }

    /**
     * Tells when headers just arrived.
     */
    public function isFirst(): bool
    {
        return $this->isFirst;
    }

    /**
     * Tells when the body just completed.
     */
    public function isLast(): bool
    {
        return $this->isLast;
    }

    /**
     * Returns a [status code, headers] tuple when a 1xx status code was received.
     * 
     * @return array{0: int, 1: array<string, list<string>>}|null
     */
    public function getInformationalStatus(): ?array
    {
        return $this->informationalStatus;
    }

    /**
     * Returns the content of the response chunk.
     * 
     * @throws \RuntimeException on error
     */
    public function getContent(): string
    {
        if ($this->error !== null) {
            throw new \RuntimeException($this->error);
        }
        
        return $this->content;
    }

    /**
     * Returns the offset of the chunk in the full body.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Returns an error message when the chunk is in error.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Creates a first chunk (headers received).
     */
    public static function createFirst(string $content = ''): self
    {
        return new self($content, true, false, 0);
    }

    /**
     * Creates a last chunk (response complete).
     */
    public static function createLast(string $content = '', int $offset = 0): self
    {
        return new self($content, false, true, $offset);
    }

    /**
     * Creates an error chunk.
     */
    public static function createError(string $error): self
    {
        return new self('', false, true, 0, $error);
    }

    /**
     * Creates a timeout chunk.
     */
    public static function createTimeout(): self
    {
        return new self('', false, false, 0, null, true);
    }
}






