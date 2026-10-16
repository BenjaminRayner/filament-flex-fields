<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Runtime\CircuitBreaker;
use Bjanczak\FilamentFlexFields\Support\Runtime\CircuitOpenException;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || ! function_exists('pcntl_fork')) {
    throw new RuntimeException('This two-process compatibility probe requires CLI PHP with pcntl.');
}

$directory = sys_get_temp_dir().'/fff-circuit-probe-'.bin2hex(random_bytes(8));
$files = new Filesystem;
$files->makeDirectory($directory, 0755, true);
$app = new Application(dirname(__DIR__, 2));
$app->instance('config', new Repository(['cache' => [
    'default' => 'file',
    'stores' => ['file' => ['driver' => 'file', 'path' => $directory.'/cache', 'lock_path' => $directory.'/locks']],
    'prefix' => 'fff-core-probe',
]]));
$app->instance('files', $files);
$app->register(CacheServiceProvider::class);
Facade::setFacadeApplication($app);
$circuit = new CircuitBreaker('two-process-recovery', 1, 1);
$child = -1;

try {
    try {
        $circuit->run(fn () => throw new RuntimeException('initial upstream failure'));
    } catch (RuntimeException) {
    }
    Carbon::setTestNow(now()->addSeconds(2));
    $child = pcntl_fork();
    if ($child === -1) {
        throw new RuntimeException('Cannot start the recovery process.');
    }
    if ($child === 0) {
        try {
            $circuit->run(function () use ($directory): string {
                file_put_contents($directory.'/ready', 'probe owns lease');
                $deadline = microtime(true) + 5;
                while (! is_file($directory.'/release')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Parent did not release the recovery probe.');
                    }
                    usleep(1000);
                }

                return 'recovered';
            });
            exit(0);
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage().PHP_EOL);
            exit(1);
        }
    }
    $deadline = microtime(true) + 5;
    while (! is_file($directory.'/ready')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Recovery process did not acquire its lease.');
        }
        usleep(1000);
    }
    $blocked = false;
    try {
        $circuit->run(fn () => 'second process must not call upstream');
    } catch (CircuitOpenException) {
        $blocked = true;
    }
    file_put_contents($directory.'/release', 'release');
    pcntl_waitpid($child, $status);
    $child = -1;
    if (! $blocked || ! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0
        || $circuit->run(fn () => 'healthy') !== 'healthy') {
        throw new RuntimeException('Shared recovery lease or post-recovery state failed.');
    }
    echo 'PASS: two PHP processes share a file-cache recovery lease; exactly one probe executes and recovery is visible to both.'.PHP_EOL;
} finally {
    if ($child > 0) {
        file_put_contents($directory.'/release', 'release');
        pcntl_waitpid($child, $status);
    }
    Carbon::setTestNow();
    $files->deleteDirectory($directory);
}
