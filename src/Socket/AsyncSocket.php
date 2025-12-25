<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Socket;

use Swoole\Coroutine\Socket;
use Swoole\Coroutine\Client;

/**
 * Async Socket operations using Swoole Coroutines
 * High-performance TCP/UDP socket communication
 * 
 * @since Swoole 6.1
 */
class AsyncSocket
{
    private ?Socket $socket = null;
    private int $type;
    private float $timeout;

    // Constants defined conditionally to avoid errors when Swoole is not loaded
    public const TYPE_TCP = 1; // SWOOLE_SOCK_TCP
    public const TYPE_UDP = 2; // SWOOLE_SOCK_UDP
    public const TYPE_TCP6 = 3; // SWOOLE_SOCK_TCP6
    public const TYPE_UDP6 = 4; // SWOOLE_SOCK_UDP6
    public const TYPE_UNIX_STREAM = 5; // SWOOLE_SOCK_UNIX_STREAM
    public const TYPE_UNIX_DGRAM = 6; // SWOOLE_SOCK_UNIX_DGRAM

    public function __construct(?int $type = null, float $timeout = 5.0)
    {
        // Use Swoole constants if available, otherwise use our fallback values
        if ($type === null) {
            $type = \defined('SWOOLE_SOCK_TCP') ? \SWOOLE_SOCK_TCP : self::TYPE_TCP;
        }
        $this->type = $type;
        $this->timeout = $timeout;
    }

    /**
     * Connect to remote host
     */
    public function connect(string $host, int $port): bool
    {
        $this->socket = new Socket($this->getSocketDomain(), $this->getSocketType());
        $this->socket->setOption(\SOL_SOCKET, \SO_RCVTIMEO, ['sec' => (int) $this->timeout, 'usec' => 0]);
        $this->socket->setOption(\SOL_SOCKET, \SO_SNDTIMEO, ['sec' => (int) $this->timeout, 'usec' => 0]);
        
        return $this->socket->connect($host, $port, $this->timeout);
    }

    /**
     * Send data
     */
    public function send(string $data): int|false
    {
        if ($this->socket === null) {
            return false;
        }

        return $this->socket->sendAll($data, $this->timeout);
    }

    /**
     * Receive data
     */
    public function recv(int $length = 65535): string|false
    {
        if ($this->socket === null) {
            return false;
        }

        return $this->socket->recvAll($length, $this->timeout);
    }

    /**
     * Receive line (until newline)
     */
    public function recvLine(int $maxLength = 8192): string|false
    {
        if ($this->socket === null) {
            return false;
        }

        $buffer = '';
        while (\strlen($buffer) < $maxLength) {
            $char = $this->socket->recv(1, $this->timeout);
            if ($char === false || $char === '') {
                break;
            }
            $buffer .= $char;
            if ($char === "\n") {
                break;
            }
        }

        return $buffer ?: false;
    }

    /**
     * Send and receive (request-response pattern)
     */
    public function sendRecv(string $data, int $recvLength = 65535): string|false
    {
        $sent = $this->send($data);
        if ($sent === false) {
            return false;
        }

        return $this->recv($recvLength);
    }

    /**
     * Close connection
     */
    public function close(): bool
    {
        if ($this->socket === null) {
            return true;
        }

        $result = $this->socket->close();
        $this->socket = null;
        return $result;
    }

    /**
     * Get last error code
     */
    public function getErrorCode(): int
    {
        return $this->socket?->errCode ?? 0;
    }

    /**
     * Get last error message
     */
    public function getErrorMessage(): string
    {
        return $this->socket?->errMsg ?? '';
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->socket !== null && $this->socket->checkLiveness();
    }

    /**
     * Get socket domain
     */
    private function getSocketDomain(): int
    {
        return match ($this->type) {
            self::TYPE_TCP, self::TYPE_UDP => \AF_INET,
            self::TYPE_TCP6, self::TYPE_UDP6 => \AF_INET6,
            self::TYPE_UNIX_STREAM, self::TYPE_UNIX_DGRAM => \AF_UNIX,
            default => \AF_INET,
        };
    }

    /**
     * Get socket type
     */
    private function getSocketType(): int
    {
        return match ($this->type) {
            self::TYPE_TCP, self::TYPE_TCP6, self::TYPE_UNIX_STREAM => \SOCK_STREAM,
            self::TYPE_UDP, self::TYPE_UDP6, self::TYPE_UNIX_DGRAM => \SOCK_DGRAM,
            default => \SOCK_STREAM,
        };
    }
}

/**
 * Async DNS resolver
 */
class AsyncDNS
{
    /**
     * Resolve hostname to IP addresses
     */
    public static function resolve(string $hostname, int $family = \AF_INET, float $timeout = 5.0): array|false
    {
        return \Swoole\Coroutine\System::gethostbyname($hostname, $family, $timeout);
    }

    /**
     * Get all IP addresses for hostname
     */
    public static function resolveAll(string $hostname, int $family = \AF_INET, float $timeout = 5.0): array|false
    {
        return \Swoole\Coroutine\System::getaddrinfo($hostname, $family, \SOCK_STREAM, \IPPROTO_TCP, null, $timeout);
    }

    /**
     * Reverse DNS lookup
     */
    public static function reverse(string $ip): string|false
    {
        return \gethostbyaddr($ip);
    }

    /**
     * Check if string is valid IP
     */
    public static function isValidIp(string $ip): bool
    {
        return \filter_var($ip, \FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if string is valid IPv4
     */
    public static function isValidIpv4(string $ip): bool
    {
        return \filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Check if string is valid IPv6
     */
    public static function isValidIpv6(string $ip): bool
    {
        return \filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) !== false;
    }
}







