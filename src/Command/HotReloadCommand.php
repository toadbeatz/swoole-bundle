<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Toadbeatz\SwooleBundle\HotReload\HotReloadWatcher;
use Toadbeatz\SwooleBundle\Server\HttpServerManager;

/**
 * Command to start the Swoole server with hot-reload for development
 */
class HotReloadCommand extends Command
{
    protected static $defaultName = 'swoole:server:watch';
    protected static $defaultDescription = 'Start the Swoole server with hot-reload enabled';

    private HttpServerManager $serverManager;
    private HotReloadWatcher $hotReloadWatcher;

    public function __construct(HttpServerManager $serverManager, HotReloadWatcher $hotReloadWatcher)
    {
        parent::__construct();
        $this->serverManager = $serverManager;
        $this->hotReloadWatcher = $hotReloadWatcher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\extension_loaded('swoole')) {
            $io->error('Swoole extension is not loaded.');
            return Command::FAILURE;
        }

        $io->title('Starting Swoole Server with Hot Reload');

        // Initialize hot reload watcher
        $this->hotReloadWatcher->start(function () use ($io) {
            $io->note('File change detected, reloading server...');
            $this->reloadServer();
        });

        // Initialize server
        $this->serverManager->initialize();

        $server = $this->serverManager->getServer()->getServer();
        $io->success(\sprintf('Server listening on %s:%d with hot-reload enabled', $server->host ?? '0.0.0.0', $server->port ?? 9501));

        // Start server
        $this->serverManager->getServer()->start();

        return Command::SUCCESS;
    }

    private function reloadServer(): void
    {
        $pidFile = \sys_get_temp_dir() . '/swoole.pid';
        if (\file_exists($pidFile)) {
            $pid = (int) \file_get_contents($pidFile);
            \posix_kill($pid, \SIGUSR1); // Swoole reload signal
        }
    }
}

