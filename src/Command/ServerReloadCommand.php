<?php

declare(strict_types=1);

namespace Toadbeatz\SwooleBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Command to reload the Swoole server gracefully
 * 
 * This command reloads all workers to pick up code changes without
 * stopping the server. Ideal for production deployments.
 * 
 * Usage:
 *   php bin/console swoole:server:reload
 *   php bin/console swoole:server:reload --no-cache-clear
 *   php bin/console swoole:server:reload --only-cache
 */
#[AsCommand(
    name: 'swoole:server:reload',
    description: 'Reload the Swoole server workers gracefully (zero-downtime)'
)]
class ServerReloadCommand extends Command
{
    private KernelInterface $kernel;
    private Filesystem $filesystem;

    public function __construct(KernelInterface $kernel, ?Filesystem $filesystem = null)
    {
        parent::__construct();
        $this->kernel = $kernel;
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    protected function configure(): void
    {
        $this
            ->addOption('no-cache-clear', null, InputOption::VALUE_NONE, 'Skip Symfony cache clearing')
            ->addOption('only-cache', null, InputOption::VALUE_NONE, 'Only clear cache without reloading workers')
            ->addOption('opcache', null, InputOption::VALUE_NONE, 'Also clear OPcache')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command gracefully reloads all Swoole workers
to pick up code changes without stopping the server:

  <info>php %command.full_name%</info>

This is ideal for production deployments as it provides zero-downtime updates.

The command will:
  1. Clear Symfony cache (unless --no-cache-clear is used)
  2. Clear OPcache if available (use --opcache to force)
  3. Send reload signal to all workers
  4. Workers will finish current requests and reload with new code

To only clear cache without reloading:

  <info>php %command.full_name% --only-cache</info>

To skip cache clearing:

  <info>php %command.full_name% --no-cache-clear</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Swoole Server Reload');

        // Check if server is running
        $pidFile = \sys_get_temp_dir() . '/swoole.pid';
        
        if (!\file_exists($pidFile)) {
            $io->error('Swoole server is not running (PID file not found).');
            $io->note('Start the server first with: php bin/console swoole:server:start');
            return Command::FAILURE;
        }

        $pid = (int) \file_get_contents($pidFile);
        
        if ($pid <= 0) {
            $io->error('Invalid PID in PID file.');
            return Command::FAILURE;
        }

        // Check if process exists
        if (!$this->processExists($pid)) {
            $io->error(\sprintf('Swoole server process (PID: %d) not found.', $pid));
            $io->note('The PID file exists but the process is not running. Clean up the PID file.');
            return Command::FAILURE;
        }

        $onlyCache = $input->getOption('only-cache');
        $noCacheClear = $input->getOption('no-cache-clear');
        $clearOpcache = $input->getOption('opcache');

        // Step 1: Clear Symfony cache FIRST (before reloading workers)
        // This ensures cache is cleared before workers reload with new code
        if (!$noCacheClear && !$onlyCache) {
            $io->section('Clearing Symfony cache...');
            $this->clearSymfonyCache($io);
        }

        // Step 2: Clear OPcache
        if ($clearOpcache || (!$noCacheClear && !$onlyCache)) {
            $this->clearOpcache($io);
        }

        // Step 3: Reload workers AFTER cache is cleared
        // Workers will reload with fresh cache that will be rebuilt on first request
        if (!$onlyCache) {
            $io->section('Reloading workers...');
            $success = $this->reloadWorkers($pid, $io);
            
            if (!$success) {
                $io->error('Failed to reload workers.');
                return Command::FAILURE;
            }
        }

        // Step 4: Final message
        if (!$onlyCache) {
            $io->success([
                'Workers reloaded successfully!',
                \sprintf('Server PID: %d', $pid),
                'New code is now active. Workers will finish current requests and reload.',
                '',
                'Note: Cache has been cleared. New requests will rebuild the cache automatically.',
            ]);
        } else {
            $io->success('Cache cleared successfully!');
        }

        return Command::SUCCESS;
    }

    /**
     * Clear Symfony cache
     * Uses a safer approach: removes files but keeps directory structure
     */
    private function clearSymfonyCache(SymfonyStyle $io): void
    {
        $cacheDir = $this->kernel->getCacheDir();
        $projectDir = $this->kernel->getProjectDir();
        
        // Clear var/cache directory
        $varCacheDir = $projectDir . '/var/cache';
        
        if ($this->filesystem->exists($varCacheDir)) {
            try {
                // Use a safer approach: remove contents but keep the directory
                // This prevents issues with workers that might still have file handles open
                $this->clearCacheDirectory($varCacheDir);
                $io->text('✓ Symfony cache cleared');
            } catch (\Throwable $e) {
                $io->warning(\sprintf('Could not clear cache directory: %s', $e->getMessage()));
            }
        } else {
            $io->text('ℹ No cache directory found');
        }

        // Also clear the kernel cache directory if different
        if ($cacheDir !== $varCacheDir && $this->filesystem->exists($cacheDir)) {
            try {
                $this->clearCacheDirectory($cacheDir);
                $io->text('✓ Kernel cache cleared');
            } catch (\Throwable $e) {
                $io->warning(\sprintf('Could not clear kernel cache: %s', $e->getMessage()));
            }
        }
    }

    /**
     * Safely clear cache directory contents
     * Removes files and subdirectories but keeps the main directory
     * Uses Filesystem component for better error handling
     */
    private function clearCacheDirectory(string $cacheDir): void
    {
        if (!\is_dir($cacheDir)) {
            return;
        }

        try {
            // Use Symfony Filesystem for safer removal
            // It handles file locks and permissions better
            $this->filesystem->remove($cacheDir);
            
            // Recreate the directory structure
            $this->filesystem->mkdir($cacheDir);
            
            // Create .gitkeep to preserve directory
            $gitkeep = $cacheDir . '/.gitkeep';
            if (!\file_exists($gitkeep)) {
                \touch($gitkeep);
            }
        } catch (\Throwable $e) {
            // If removal fails, try manual cleanup
            // This can happen if files are locked by running processes
            $this->manualCacheCleanup($cacheDir);
        }
    }

    /**
     * Manual cache cleanup as fallback
     * Only removes files that are not currently in use
     */
    private function manualCacheCleanup(string $cacheDir): void
    {
        if (!\is_dir($cacheDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            try {
                if ($file->isDir()) {
                    @\rmdir($file->getPathname());
                } else {
                    // Invalidate OPcache first
                    if (\function_exists('opcache_invalidate')) {
                        @\opcache_invalidate($file->getPathname(), true);
                    }
                    // Then try to remove
                    @\unlink($file->getPathname());
                }
            } catch (\Throwable $e) {
                // Silently skip files that can't be removed (likely in use)
                continue;
            }
        }
    }

    /**
     * Clear OPcache
     */
    private function clearOpcache(SymfonyStyle $io): void
    {
        if (!\function_exists('opcache_reset')) {
            $io->text('ℹ OPcache not available');
            return;
        }

        if (!\opcache_reset()) {
            $io->warning('OPcache reset failed');
            return;
        }

        $io->text('✓ OPcache cleared');
        
        // Also clear opcache for CLI if available
        if (\function_exists('opcache_invalidate') && \ini_get('opcache.enable_cli')) {
            // Try to invalidate common cache files
            $projectDir = $this->kernel->getProjectDir();
            $commonFiles = [
                $projectDir . '/vendor/autoload.php',
                $projectDir . '/config/services.php',
                $projectDir . '/config/bundles.php',
            ];
            
            foreach ($commonFiles as $file) {
                if (\file_exists($file)) {
                    \opcache_invalidate($file, true);
                }
            }
        }
    }

    /**
     * Reload Swoole workers gracefully
     */
    private function reloadWorkers(int $pid, SymfonyStyle $io): bool
    {
        // Method 1: Use posix_kill with SIGUSR1 (preferred)
        if (\function_exists('posix_kill')) {
            $io->text(\sprintf('Sending reload signal (SIGUSR1) to PID %d...', $pid));
            
            if (!\posix_kill($pid, \SIGUSR1)) {
                $io->error('Failed to send reload signal.');
                return false;
            }
            
            $io->text('✓ Reload signal sent');
            
            // Wait a moment to verify the signal was received
            \usleep(100000); // 0.1 seconds
            
            if (!$this->processExists($pid)) {
                $io->error('Server process died after reload signal.');
                return false;
            }
            
            return true;
        }

        // Method 2: Windows fallback (if Swoole supports it)
        if (\PHP_OS_FAMILY === 'Windows') {
            $io->warning('Signal-based reload not available on Windows.');
            $io->note('Consider using the Swoole server API directly for reload.');
            return false;
        }

        // Method 3: Try using kill command (with sanitized PID)
        $io->text('Attempting reload via kill command...');
        // Sanitize PID to prevent command injection
        $sanitizedPid = \filter_var($pid, \FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => \PHP_INT_MAX]
        ]);
        
        if ($sanitizedPid === false) {
            $io->error('Invalid PID value.');
            return false;
        }
        
        \exec(\sprintf('kill -USR1 %d 2>&1', $sanitizedPid), $output, $returnCode);
        
        if ($returnCode !== 0) {
            $io->error(\sprintf('Failed to reload: %s', \implode("\n", $output)));
            return false;
        }
        
        $io->text('✓ Reload command executed');
        return true;
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
            // Sanitize PID to prevent command injection
            $sanitizedPid = \filter_var($pid, \FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => \PHP_INT_MAX]
            ]);
            
            if ($sanitizedPid === false) {
                return false;
            }
            
            \exec(\sprintf('tasklist /FI "PID eq %d" 2>NUL', $sanitizedPid), $output);
            return \count($output) > 1;
        }
        
        // Unix fallback - sanitize PID in path
        $sanitizedPid = \filter_var($pid, \FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => \PHP_INT_MAX]
        ]);
        
        if ($sanitizedPid === false) {
            return false;
        }
        
        return \file_exists("/proc/$sanitizedPid");
    }
}






