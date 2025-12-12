<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to stop the Swoole HTTP server
 * 
 * Usage:
 *   php bin/console swoole:server:stop
 *   php bin/console swoole:server:stop --force
 */
#[AsCommand(
    name: 'swoole:server:stop',
    description: 'Stop the Swoole HTTP server'
)]
class ServerStopCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force kill the server')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command stops the Swoole HTTP server:

  <info>php %command.full_name%</info>

To force kill the server:

  <info>php %command.full_name% --force</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pidFile = \sys_get_temp_dir() . '/swoole.pid';

        if (!\file_exists($pidFile)) {
            $io->warning('Swoole server is not running (PID file not found).');
            return Command::FAILURE;
        }

        $pid = (int) \file_get_contents($pidFile);

        if ($pid <= 0) {
            $io->error('Invalid PID in PID file.');
            \unlink($pidFile);
            return Command::FAILURE;
        }

        // Check if process exists (cross-platform)
        $processExists = $this->processExists($pid);
        
        if (!$processExists) {
            $io->warning(\sprintf('Process %d not found. Cleaning up PID file.', $pid));
            \unlink($pidFile);
            return Command::SUCCESS;
        }

        $force = $input->getOption('force');
        $io->text(\sprintf('Stopping Swoole server (PID: %d)...', $pid));

        // Send appropriate signal
        if ($force) {
            $this->killProcess($pid, true);
            $io->success('Server forcefully terminated.');
        } else {
            // Graceful shutdown
            $this->killProcess($pid, false);
            
            // Wait for graceful shutdown (max 5 seconds)
            $waited = 0;
            while ($this->processExists($pid) && $waited < 50) {
                \usleep(100000); // 0.1 seconds
                $waited++;
            }
            
            if ($this->processExists($pid)) {
                $io->warning('Graceful shutdown timed out, forcing...');
                $this->killProcess($pid, true);
            }
            
            $io->success('Swoole server stopped.');
        }

        // Clean up PID file
        if (\file_exists($pidFile)) {
            \unlink($pidFile);
        }

        return Command::SUCCESS;
    }

    /**
     * Check if a process exists (cross-platform)
     */
    private function processExists(int $pid): bool
    {
        if (\function_exists('posix_kill')) {
            return \posix_kill($pid, 0);
        }
        
        // Windows fallback
        if (\PHP_OS_FAMILY === 'Windows') {
            \exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
            return \count($output) > 1;
        }
        
        // Unix fallback
        return \file_exists("/proc/$pid");
    }

    /**
     * Kill a process (cross-platform)
     */
    private function killProcess(int $pid, bool $force): void
    {
        if (\function_exists('posix_kill')) {
            \posix_kill($pid, $force ? \SIGKILL : \SIGTERM);
            return;
        }
        
        // Windows fallback
        if (\PHP_OS_FAMILY === 'Windows') {
            $flag = $force ? '/F' : '';
            \exec("taskkill $flag /PID $pid 2>NUL");
            return;
        }
        
        // Unix fallback
        $signal = $force ? 9 : 15;
        \exec("kill -$signal $pid 2>/dev/null");
    }
}
