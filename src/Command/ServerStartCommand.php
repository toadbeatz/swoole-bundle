<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Toadbeatz\SwooleBundle\Server\HttpServerManager;

/**
 * Command to start the Swoole server
 */
class ServerStartCommand extends Command
{
    protected static $defaultName = 'swoole:server:start';
    protected static $defaultDescription = 'Start the Swoole HTTP server';

    private HttpServerManager $serverManager;

    public function __construct(HttpServerManager $serverManager)
    {
        parent::__construct();
        $this->serverManager = $serverManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption('daemonize', 'd', InputOption::VALUE_NONE, 'Run server as daemon')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Server host', null)
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Server port', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if Swoole extension is loaded
        if (!\extension_loaded('swoole')) {
            $io->error('Swoole extension is not loaded. Please install it first.');
            return Command::FAILURE;
        }

        // Check Swoole version
        $version = \swoole_version();
        if (\version_compare($version, '6.1', '<')) {
            $io->warning(\sprintf('Swoole version %s detected. Version 6.1+ is recommended.', $version));
        }

        $io->success(\sprintf('Starting Swoole server (Swoole %s)...', $version));

        // Initialize server
        $this->serverManager->initialize();

        // Get server instance for display
        $server = $this->serverManager->getServer()->getServer();
        $host = $server->host ?? '0.0.0.0';
        $port = $server->port ?? 9501;

        $io->info(\sprintf('Server listening on %s:%d', $host, $port));

        // Start server (this will block)
        $this->serverManager->getServer()->start();

        return Command::SUCCESS;
    }
}

