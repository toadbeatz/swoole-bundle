<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Toadbeatz\SwooleBundle\HotReload\HotReloadWatcher;
use Toadbeatz\SwooleBundle\Server\HttpServerManager;

/**
 * Command to start the Swoole server with hot-reload for development
 * 
 * Usage:
 *   php bin/console swoole:server:watch
 *   php bin/console swoole:server:watch --no-clear-cache
 */
#[AsCommand(
    name: 'swoole:server:watch',
    description: 'Start the Swoole server with hot-reload enabled'
)]
class HotReloadCommand extends Command
{
    private HttpServerManager $serverManager;
    private HotReloadWatcher $hotReloadWatcher;

    public function __construct(HttpServerManager $serverManager, HotReloadWatcher $hotReloadWatcher)
    {
        parent::__construct();
        $this->serverManager = $serverManager;
        $this->hotReloadWatcher = $hotReloadWatcher;
    }

    protected function configure(): void
    {
        $this
            ->addOption('no-clear-cache', null, InputOption::VALUE_NONE, 'Do not clear cache on reload')
            ->addOption('poll-interval', null, InputOption::VALUE_OPTIONAL, 'File poll interval in ms', 1000)
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command starts the Swoole server with hot-reload:

  <info>php %command.full_name%</info>

This is ideal for development as it automatically reloads workers
when files in the watched directories change.

Default watched directories: src, config, templates

To customize poll interval:

  <info>php %command.full_name% --poll-interval=500</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\extension_loaded('swoole')) {
            $io->error('Swoole extension is not loaded.');
            return Command::FAILURE;
        }

        $version = \swoole_version();
        
        $io->title('Swoole Server with Hot Reload');
        $io->text([
            \sprintf('Swoole version: <info>%s</info>', $version),
            \sprintf('PHP version: <info>%s</info>', PHP_VERSION),
            '<comment>File changes will trigger automatic reload</comment>',
        ]);

        // Initialize hot reload watcher
        $reloadCount = 0;
        $this->hotReloadWatcher->start(function (array $changedFiles) use ($io, &$reloadCount) {
            $reloadCount++;
            $io->newLine();
            $io->note(\sprintf(
                '[Reload #%d] %d file(s) changed, reloading workers...',
                $reloadCount,
                \count($changedFiles)
            ));
            
            foreach (\array_slice($changedFiles, 0, 5) as $file) {
                $io->text(\sprintf('  → %s', \basename($file)));
            }
            
            if (\count($changedFiles) > 5) {
                $io->text(\sprintf('  ... and %d more', \count($changedFiles) - 5));
            }
            
            $this->reloadServer();
        });

        // Initialize server
        $this->serverManager->initialize();

        $server = $this->serverManager->getServer()->getServer();
        $host = $server->host ?? '0.0.0.0';
        $port = $server->port ?? 9501;

        $io->success(\sprintf('Server listening on http://%s:%d with hot-reload enabled', $host, $port));
        $io->note('Press Ctrl+C to stop the server');

        // Start server
        $this->serverManager->getServer()->start();

        return Command::SUCCESS;
    }

    private function reloadServer(): void
    {
        $pidFile = \sys_get_temp_dir() . '/swoole.pid';
        
        if (\file_exists($pidFile)) {
            $pid = (int) \file_get_contents($pidFile);
            
            if ($pid > 0 && \function_exists('posix_kill')) {
                // SIGUSR1 triggers worker reload in Swoole
                \posix_kill($pid, \SIGUSR1);
            }
        }
    }
}
