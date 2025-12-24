<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Cache;

/**
 * Alias class for SwooleCacheAdapter
 * 
 * This class provides a simpler name for the cache implementation.
 * Use SwooleCacheAdapter directly for dependency injection.
 * 
 * @deprecated Use SwooleCacheAdapter instead. This class is kept for backward compatibility.
 */
class SwooleCache extends SwooleCacheAdapter
{
    // This is just an alias class for easier discovery
    // All functionality is inherited from SwooleCacheAdapter
}


