<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\WebSocket;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * WebSocket Handler for Swoole
 */
class WebSocketHandler
{
    private array $connections = [];
    private array $rooms = [];

    public function onOpen(Server $server, $request): void
    {
        $fd = $request->fd;
        $this->connections[$fd] = [
            'id' => $fd,
            'connected_at' => \time(),
        ];

        $server->push($fd, \json_encode([
            'type' => 'welcome',
            'fd' => $fd,
            'message' => 'Connected to Swoole WebSocket server',
        ]));
    }

    public function onMessage(Server $server, Frame $frame): void
    {
        $data = \json_decode($frame->data, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'join':
                $this->joinRoom($frame->fd, $data['room'] ?? 'default');
                break;
            
            case 'leave':
                $this->leaveRoom($frame->fd, $data['room'] ?? 'default');
                break;
            
            case 'broadcast':
                $this->broadcast($server, $frame->fd, $data['message'] ?? '', $data['room'] ?? null);
                break;
            
            case 'ping':
                $server->push($frame->fd, \json_encode(['type' => 'pong']));
                break;
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        // Remove from all rooms
        foreach ($this->rooms as $room => $connections) {
            $this->rooms[$room] = \array_filter($connections, fn($id) => $id !== $fd);
        }

        unset($this->connections[$fd]);
    }

    private function joinRoom(int $fd, string $room): void
    {
        if (!isset($this->rooms[$room])) {
            $this->rooms[$room] = [];
        }

        if (!\in_array($fd, $this->rooms[$room], true)) {
            $this->rooms[$room][] = $fd;
        }
    }

    private function leaveRoom(int $fd, string $room): void
    {
        if (isset($this->rooms[$room])) {
            $this->rooms[$room] = \array_filter($this->rooms[$room], fn($id) => $id !== $fd);
        }
    }

    private function broadcast(Server $server, int $fromFd, string $message, ?string $room = null): void
    {
        $data = \json_encode([
            'type' => 'message',
            'from' => $fromFd,
            'message' => $message,
            'timestamp' => \time(),
        ]);

        if ($room !== null && isset($this->rooms[$room])) {
            // Broadcast to room
            foreach ($this->rooms[$room] as $fd) {
                if ($fd !== $fromFd && $server->isEstablished($fd)) {
                    $server->push($fd, $data);
                }
            }
        } else {
            // Broadcast to all
            foreach ($this->connections as $fd => $connection) {
                if ($fd !== $fromFd && $server->isEstablished($fd)) {
                    $server->push($fd, $data);
                }
            }
        }
    }
}

