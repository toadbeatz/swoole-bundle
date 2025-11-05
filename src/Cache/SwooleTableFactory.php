<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Cache;

/**
 * Factory for creating Swoole Table instances
 */
class SwooleTableFactory
{
    public static function createCacheTable(): SwooleTable
    {
        return new SwooleTable(1000000);
    }

    public static function createSessionTable(): SwooleTable
    {
        return new SwooleTable(100000);
    }
}

