<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Cache;

use Symfony\Contracts\Cache\ItemInterface;

/**
 * Cache Item for Swoole Cache Adapter
 */
class SwooleCacheItem implements ItemInterface
{
    private string $key;
    private mixed $value;
    private bool $isHit = false;
    private ?int $ttl = null;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        if ($expiration) {
            $this->ttl = $expiration->getTimestamp() - \time();
        }
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time instanceof \DateInterval) {
            $this->ttl = (new \DateTime())->add($time)->getTimestamp() - \time();
        } elseif (\is_int($time)) {
            $this->ttl = $time;
        }
        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl ?? 3600;
    }
}

