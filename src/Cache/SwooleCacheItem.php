<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Cache;

use Symfony\Contracts\Cache\ItemInterface;

/**
 * Cache Item for Swoole Cache Adapter
 * 
 * Implements Symfony's ItemInterface with full tag support
 * for cache invalidation and metadata retrieval.
 */
class SwooleCacheItem implements ItemInterface
{
    private string $key;
    private mixed $value = null;
    private bool $isHit = false;
    private ?int $ttl = null;
    private int $createdAt;
    
    /** @var array<string, string> */
    private array $tags = [];

    public function __construct(string $key)
    {
        $this->key = $key;
        $this->createdAt = \time();
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
        if ($expiration !== null) {
            $this->ttl = $expiration->getTimestamp() - \time();
        } else {
            $this->ttl = null;
        }
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time instanceof \DateInterval) {
            $this->ttl = (new \DateTime())->add($time)->getTimestamp() - \time();
        } elseif (\is_int($time)) {
            $this->ttl = $time;
        } else {
            $this->ttl = null;
        }
        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl ?? 3600;
    }

    /**
     * Tags the cache item for invalidation purposes.
     * 
     * @param string|iterable<string> $tags One or more tags to associate with this item
     * @return static
     */
    public function tag(string|iterable $tags): static
    {
        if (\is_string($tags)) {
            $tags = [$tags];
        }
        
        foreach ($tags as $tag) {
            if (!\is_string($tag)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Cache tag must be a string, "%s" given.',
                    \get_debug_type($tag)
                ));
            }
            
            if ($tag === '') {
                throw new \InvalidArgumentException('Cache tag cannot be empty.');
            }
            
            $this->tags[$tag] = $tag;
        }
        
        return $this;
    }

    /**
     * Returns metadata about the cache item.
     * 
     * @return array{expiry: ?int, ctime: int, tags: array<int, string>}
     */
    public function getMetadata(): array
    {
        return [
            'expiry' => $this->ttl !== null ? $this->createdAt + $this->ttl : null,
            'ctime' => $this->createdAt,
            'tags' => \array_values($this->tags),
        ];
    }

    /**
     * Get all tags associated with this item.
     * 
     * @return array<string>
     */
    public function getTags(): array
    {
        return \array_values($this->tags);
    }
}
