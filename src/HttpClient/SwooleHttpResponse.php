<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\HttpClient;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Swoole\Coroutine\Http\Client as SwooleClient;

/**
 * Swoole HTTP Response implementation
 */
class SwooleHttpResponse implements ResponseInterface
{
    private string $method;
    private string $url;
    private array $options;
    private ?array $info = null;
    private ?string $content = null;
    private ?array $headers = null;

    public function __construct(string $method, string $url, array $options)
    {
        $this->method = $method;
        $this->url = $url;
        $this->options = $options;
    }

    public function getStatusCode(): int
    {
        $this->executeRequest();
        return $this->info['http_code'] ?? 0;
    }

    public function getHeaders(bool $throw = true): array
    {
        $this->executeRequest();
        return $this->headers ?? [];
    }

    public function getContent(bool $throw = true): string
    {
        $this->executeRequest();
        return $this->content ?? '';
    }

    public function toArray(bool $throw = true): array
    {
        return \json_decode($this->getContent($throw), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function cancel(): void
    {
        // Swoole doesn't support cancellation easily, but we can mark as cancelled
    }

    public function getInfo(?string $type = null): mixed
    {
        $this->executeRequest();
        
        if ($type === null) {
            return $this->info;
        }

        return $this->info[$type] ?? null;
    }

    private function executeRequest(): void
    {
        if ($this->content !== null) {
            return; // Already executed
        }

        $parsedUrl = \parse_url($this->url);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? (($parsedUrl['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $ssl = ($parsedUrl['scheme'] ?? 'http') === 'https';
        $path = $parsedUrl['path'] ?? '/';
        if (isset($parsedUrl['query'])) {
            $path .= '?' . $parsedUrl['query'];
        }

        $client = new SwooleClient($host, $port, $ssl);
        
        // Set headers
        if (isset($this->options['headers'])) {
            $client->setHeaders($this->options['headers']);
        }

        // Set body
        $body = null;
        if (isset($this->options['body'])) {
            $body = $this->options['body'];
        } elseif (isset($this->options['json'])) {
            $body = \json_encode($this->options['json']);
            $client->setHeaders(['Content-Type' => 'application/json']);
        }

        // Execute request
        $method = \strtoupper($this->method);
        $client->setMethod($method);
        
        if ($body) {
            $client->setData($body);
        }

        $client->execute($path);

        // Store response
        $this->content = $client->body;
        $this->headers = $client->headers ?? [];
        $this->info = [
            'http_code' => $client->statusCode ?? 0,
            'url' => $this->url,
            'content_type' => $this->headers['content-type'] ?? null,
            'total_time' => $client->getTime() ?? 0,
        ];

        $client->close();
    }
}

