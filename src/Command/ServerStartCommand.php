<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Toadbeatz\SwooleBundle\Server\HttpServerManager;

/**
 * Command to start the Swoole HTTP server
 * 
 * Usage:
 *   php bin/console swoole:server:start
 *   php bin/console swoole:server:start -d (daemonize)
 *   php bin/console swoole:server:start --port=8080
 */
#[AsCommand(
    name: 'swoole:server:start',
    description: 'Start the Swoole HTTP server'
)]
class ServerStartCommand extends Command
{
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
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Server port', null)
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command starts the Swoole HTTP server:

  <info>php %command.full_name%</info>

To run as a daemon (background process):

  <info>php %command.full_name% -d</info>

To specify a custom port:

  <info>php %command.full_name% --port=8080</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if Swoole extension is loaded
        if (!\extension_loaded('swoole')) {
            $io->error('Swoole extension is not loaded. Please install it first.');
            $io->note('Installation: pecl install swoole');
            return Command::FAILURE;
        }

        // Check Swoole version
        $version = \swoole_version();
        if (\version_compare($version, '6.0', '<')) {
            $io->warning(\sprintf(
                'Swoole version %s detected. Version 6.0+ is recommended for best performance.',
                $version
            ));
        }

        $io->title('Swoole HTTP Server');
        $io->text(\sprintf('Swoole version: <info>%s</info>', $version));
        $io->text(\sprintf('PHP version: <info>%s</info>', PHP_VERSION));

        $io->section('Starting server...');

        // Initialize server
        $this->serverManager->initialize();

        // Get server instance for display
        $server = $this->serverManager->getServer()->getServer();
        $host = $server->host ?? '0.0.0.0';
        $port = $server->port ?? 9501;

        $io->success(\sprintf('Server listening on http://%s:%d', $host, $port));
        $io->note('Press Ctrl+C to stop the server');

        // Start server (this will block)
        $this->serverManager->getServer()->start();

        return Command::SUCCESS;
    }
}
