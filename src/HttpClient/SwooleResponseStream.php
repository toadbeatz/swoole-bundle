<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\HttpClient;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Swoole Response Stream implementation
 */
class SwooleResponseStream implements ResponseStreamInterface
{
    private iterable $responses;

    public function __construct(iterable $responses)
    {
        $this->responses = $responses;
    }

    public function key(): ResponseInterface
    {
        return $this->responses->current();
    }

    public function current(): ResponseInterface
    {
        return $this->responses->current();
    }

    public function next(): void
    {
        $this->responses->next();
    }

    public function valid(): bool
    {
        return $this->responses->valid();
    }

    public function rewind(): void
    {
        if (\is_array($this->responses)) {
            \reset($this->responses);
        } else {
            $this->responses->rewind();
        }
    }
}

