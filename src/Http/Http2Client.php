<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Http;

use Swoole\Coroutine\Http2\Client;

/**
 * HTTP/2 Client using Swoole Coroutine HTTP/2
 * High-performance multiplexed HTTP/2 connections
 * 
 * @since Swoole 6.1
 */
class Http2Client
{
    private ?Client $client = null;
    private string $host;
    private int $port;
    private bool $ssl;
    private float $timeout;
    private array $settings = [];

    public function __construct(
        string $host,
        int $port = 443,
        bool $ssl = true,
        float $timeout = 5.0
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;
        $this->timeout = $timeout;
    }

    /**
     * Connect to HTTP/2 server
     */
    public function connect(): bool
    {
        $this->client = new Client($this->host, $this->port, $this->ssl);
        
        $this->client->set([
            'timeout' => $this->timeout,
            'ssl_host_name' => $this->host,
            ...$this->settings,
        ]);

        return $this->client->connect();
    }

    /**
     * Set client settings
     */
    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Send HTTP/2 request
     */
    public function request(
        string $method,
        string $path,
        array $headers = [],
        ?string $body = null
    ): Http2Response {
        if ($this->client === null || !$this->client->connected) {
            if (!$this->connect()) {
                throw new \RuntimeException('Failed to connect to HTTP/2 server');
            }
        }

        $request = new \Swoole\Http2\Request();
        $request->method = $method;
        $request->path = $path;
        $request->headers = \array_merge([
            'host' => $this->host,
            'user-agent' => 'Swoole-HTTP2-Client/6.1',
        ], $headers);

        if ($body !== null) {
            $request->data = $body;
        }

        $streamId = $this->client->send($request);
        
        if ($streamId === false) {
            throw new \RuntimeException('Failed to send HTTP/2 request');
        }

        $response = $this->client->recv($this->timeout);
        
        if ($response === false) {
            throw new \RuntimeException('Failed to receive HTTP/2 response: ' . $this->client->errMsg);
        }

        return new Http2Response(
            $response->statusCode,
            $response->headers ?? [],
            $response->data ?? ''
        );
    }

    /**
     * GET request
     */
    public function get(string $path, array $headers = []): Http2Response
    {
        return $this->request('GET', $path, $headers);
    }

    /**
     * POST request
     */
    public function post(string $path, ?string $body = null, array $headers = []): Http2Response
    {
        return $this->request('POST', $path, $headers, $body);
    }

    /**
     * POST JSON request
     */
    public function postJson(string $path, mixed $data, array $headers = []): Http2Response
    {
        $headers['content-type'] = 'application/json';
        return $this->post($path, \json_encode($data), $headers);
    }

    /**
     * PUT request
     */
    public function put(string $path, ?string $body = null, array $headers = []): Http2Response
    {
        return $this->request('PUT', $path, $headers, $body);
    }

    /**
     * DELETE request
     */
    public function delete(string $path, array $headers = []): Http2Response
    {
        return $this->request('DELETE', $path, $headers);
    }

    /**
     * Send multiple requests concurrently (multiplexing)
     */
    public function sendMultiple(array $requests): array
    {
        if ($this->client === null || !$this->client->connected) {
            if (!$this->connect()) {
                throw new \RuntimeException('Failed to connect to HTTP/2 server');
            }
        }

        $streamIds = [];
        
        // Send all requests
        foreach ($requests as $key => $req) {
            $request = new \Swoole\Http2\Request();
            $request->method = $req['method'] ?? 'GET';
            $request->path = $req['path'];
            $request->headers = \array_merge([
                'host' => $this->host,
                'user-agent' => 'Swoole-HTTP2-Client/6.1',
            ], $req['headers'] ?? []);

            if (isset($req['body'])) {
                $request->data = $req['body'];
            }

            $streamId = $this->client->send($request);
            if ($streamId !== false) {
                $streamIds[$key] = $streamId;
            }
        }

        // Receive all responses
        $responses = [];
        foreach ($streamIds as $key => $streamId) {
            $response = $this->client->recv($this->timeout);
            if ($response !== false) {
                $responses[$key] = new Http2Response(
                    $response->statusCode,
                    $response->headers ?? [],
                    $response->data ?? ''
                );
            }
        }

        return $responses;
    }

    /**
     * Close connection
     */
    public function close(): void
    {
        if ($this->client !== null) {
            $this->client->close();
            $this->client = null;
        }
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->client !== null && $this->client->connected;
    }

    /**
     * Get error message
     */
    public function getError(): string
    {
        return $this->client?->errMsg ?? '';
    }

    /**
     * Get error code
     */
    public function getErrorCode(): int
    {
        return $this->client?->errCode ?? 0;
    }
}

/**
 * HTTP/2 Response
 */
class Http2Response
{
    public function __construct(
        private int $statusCode,
        private array $headers,
        private string $body
    ) {}

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[\strtolower($name)] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function toArray(): array
    {
        return \json_decode($this->body, true) ?? [];
    }

    public function isOk(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}







