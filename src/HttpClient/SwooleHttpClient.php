<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\HttpClient;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Swoole-based HTTP Client for Symfony
 * High-performance async HTTP client using Swoole Coroutines
 */
class SwooleHttpClient implements HttpClientInterface
{
    private array $defaultOptions = [];

    public function __construct(array $defaultOptions = [])
    {
        $this->defaultOptions = $defaultOptions;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $options = \array_merge($this->defaultOptions, $options);
        
        return new SwooleHttpResponse($method, $url, $options);
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        // For now, return a simple stream implementation
        return new SwooleResponseStream($responses);
    }

    public function withOptions(array $options): static
    {
        $new = clone $this;
        $new->defaultOptions = \array_merge($new->defaultOptions, $options);
        return $new;
    }
}

