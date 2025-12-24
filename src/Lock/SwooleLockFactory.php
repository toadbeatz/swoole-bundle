<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Lock;

/**
 * Factory for SwooleLock to prevent class loading when Swoole extension is not available
 */
class SwooleLockFactory
{
    /**
     * Create a SwooleLock instance if Swoole extension is available
     *
     * @param int $type Lock type (SwooleLock::TYPE_*)
     * @return SwooleLock
     * @throws \RuntimeException if Swoole extension is not available
     */
    public static function create(int $type = SwooleLock::TYPE_MUTEX): SwooleLock
    {
        if (!\extension_loaded('swoole') || !\class_exists('Swoole\Lock')) {
            throw new \RuntimeException('Swoole\Lock class is not available. Please ensure the Swoole extension is installed and enabled.');
        }

        return new SwooleLock($type);
    }

    /**
     * Check if Swoole Lock is available
     */
    public static function isAvailable(): bool
    {
        return \extension_loaded('swoole') && \class_exists('Swoole\Lock');
    }
}

