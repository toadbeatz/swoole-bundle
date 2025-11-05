<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to stop the Swoole server
 */
class ServerStopCommand extends Command
{
    protected static $defaultName = 'swoole:server:stop';
    protected static $defaultDescription = 'Stop the Swoole HTTP server';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pidFile = \sys_get_temp_dir() . '/swoole.pid';

        if (!\file_exists($pidFile)) {
            $io->warning('Swoole server is not running (PID file not found).');
            return Command::FAILURE;
        }

        $pid = (int) \file_get_contents($pidFile);

        if (!\posix_kill($pid, \SIGTERM)) {
            $io->error(\sprintf('Failed to stop server (PID: %d)', $pid));
            return Command::FAILURE;
        }

        // Wait a bit for graceful shutdown
        \usleep(500000); // 0.5 seconds

        if (\posix_kill($pid, 0)) {
            // Still running, force kill
            \posix_kill($pid, \SIGKILL);
        }

        \unlink($pidFile);
        $io->success('Swoole server stopped.');

        return Command::SUCCESS;
    }
}

