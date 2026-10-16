<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Unit\Core;

use Bjanczak\FilamentFlexFields\Tests\Support\Core\ExpectedScopeFailure;
use Bjanczak\FilamentFlexFields\Tests\Support\Core\WorkerJob;
use Bjanczak\FilamentFlexFields\Tests\Support\Core\WorkerReport;
use Bjanczak\FilamentFlexFields\Tests\Support\Core\WorkerServiceProvider;
use Bjanczak\FilamentFlexFields\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\WorkerOptions;

final class QueueWorkerSoakTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), WorkerServiceProvider::class];
    }

    public function test_real_database_queue_resets_core_state_after_success_and_failure(): void
    {
        $iterations = WorkerReport::iterations();
        $this->app['config']->set('queue.connections.core', [
            'driver' => 'database', 'connection' => 'testing', 'table' => 'jobs',
            'queue' => 'core', 'retry_after' => 90,
        ]);
        $this->app['config']->set('queue.failed.driver', null);
        $this->app['db']->connection()->getSchemaBuilder()->create('jobs', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        $report = $this->app->make(WorkerReport::class);
        $unexpected = [];
        $this->app['events']->listen(JobExceptionOccurred::class, static function (JobExceptionOccurred $event) use ($report, &$unexpected): void {
            if ($event->exception instanceof ExpectedScopeFailure) {
                $report->expectedFailures++;
            } elseif (count($unexpected) < 3) {
                $unexpected[] = $event->exception->getMessage();
            }
        });
        $queue = $this->app['queue']->connection('core');
        for ($iteration = 1; $iteration <= $iterations; $iteration++) {
            $queue->push(new WorkerJob($iteration), queue: 'core');
        }
        $worker = $this->app['queue.worker'];
        $worker->setCache($this->app['cache']->store());
        $signals = [];
        if (extension_loaded('pcntl')) {
            foreach ([SIGALRM, SIGQUIT, SIGTERM, SIGINT, SIGUSR2, SIGCONT] as $signal) {
                $signals[$signal] = pcntl_signal_get_handler($signal);
            }
            $asyncSignals = pcntl_async_signals();
        }
        try {
            // The surrounding suite may already be warm; growth is checked separately below.
            $exit = $worker->daemon('core', 'core', new WorkerOptions(
                memory: (int) ceil(memory_get_peak_usage(true) / 1024 / 1024) + 128,
                timeout: 0, sleep: 0, maxTries: 1,
                force: true, stopWhenEmpty: true, maxJobs: $iterations,
            ));
        } finally {
            foreach ($signals as $signal => $handler) {
                pcntl_signal($signal, $handler);
            }
            if (isset($asyncSignals)) {
                pcntl_async_signals($asyncSignals);
            }
        }
        $this->assertSame([], $unexpected);
        $this->assertSame(0, $exit);
        $this->assertSame(0, $queue->size('core'));
        $this->assertSame($iterations, $report->completed);
        $this->assertSame(intdiv($iterations, 97), $report->expectedFailures);
        $this->assertSame($iterations * 2, $report->factoryCalls);
        fwrite(STDERR, PHP_EOL.$report->describe('laravel-database-queue').PHP_EOL);
        $report->assertStable();
    }
}
