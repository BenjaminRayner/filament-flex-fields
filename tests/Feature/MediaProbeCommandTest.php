<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('rejects omitted acknowledgement or invalid profiles before accessing infrastructure', function (array $options): void {
    Storage::shouldReceive('disk')->never();
    Cache::shouldReceive('store')->never();
    expect(Artisan::call('fff:media:probe', $options))->toBe(2)
        ->and(Artisan::output())->not->toContain('private-customer-token');
})->with([
    [[]], [['--disk' => 'private-customer-token']], [['--format' => ['webp']]],
    [['--write' => true]], [['--write' => true, '--disk' => '']],
    [['--write' => true, '--lock-store' => "private-customer-token\n"]],
    [['--write' => true, '--format' => ['private-customer-token']]],
    [['--write' => true, '--format' => ['webp', 'webp']]],
]);

it('runs only selected probes without dispatching jobs or making HTTP requests', function (): void {
    Storage::fake('probe-private');
    Cache::shouldReceive('store')->never();
    Queue::fake();
    Http::preventStrayRequests();
    expect(Artisan::call('fff:media:probe', ['--write' => true, '--disk' => 'probe-private', '--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);
    expect($report['scope'])->toBe('media-active-probe')->and($report['passed'])->toBeTrue()
        ->and($report['profile'])->toBe(['disk' => 'explicit', 'lockStore' => null, 'encoder' => null, 'formats' => []])
        ->and($report['operationallyVerified'])->toBeFalse()->and($report['cleanupRequired'])->toBeFalse()
        ->and(Storage::disk('probe-private')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('continues independent lock checks after disk resolution fails and exports actionable redacted findings', function (): void {
    config()->set('cache.stores.probe-array', ['driver' => 'array']);
    expect(Artisan::call('fff:media:probe', ['--write' => true, '--disk' => 'private-customer-token', '--lock-store' => 'probe-array', '--json' => true]))->toBe(1);
    $report = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);
    expect(array_column($report['checks'], 'status', 'code'))->toMatchArray(['storage_resolution' => 'failed', 'lock_reacquire' => 'passed'])
        ->and(Artisan::output())->not->toContain('private-customer-token', 'probe-array');
});

it('prints failures, next steps and scope limits in the terminal', function (): void {
    expect(Artisan::call('fff:media:probe', ['--write' => true, '--disk' => 'private-customer-token']))->toBe(1)
        ->and(Artisan::output())->toContain('Diagnostic run:', 'FAIL', 'Next step:', 'cross-host sharing', 'Workers, schedulers')
        ->not->toContain('private-customer-token');
});
