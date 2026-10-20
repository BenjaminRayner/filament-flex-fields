<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaConfigurationDoctor;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDoctorOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaExtensionState;
use Bjanczak\FilamentFlexFields\Tests\Support\MediaDoctorEnvironment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => MediaDoctorEnvironment::configure());

it('exports a redacted profile with explicitly incomplete operational verification', function (): void {
    expect(Artisan::call('fff:media:doctor', ['--queued' => true, '--custom-encoder' => true, '--distributed' => true, '--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);

    expect($report['configurationValid'])->toBeTrue()
        ->and($report['operationallyVerified'])->toBeFalse()
        ->and($report['readOnly'])->toBeTrue()
        ->and($report['profile'])->toMatchArray(['images' => true, 'queued' => true, 'distributed' => true, 'encoder' => 'custom', 'jobTimeout' => 60])
        ->and($report['counts']['unverified'])->toBeGreaterThan(0)
        ->and(array_column($report['checks'], 'code'))->toContain('worker_liveness_unverified', 'scheduler_liveness_unverified', 'shared_context_unverified')
        ->and($report['versions']['php'])->toBe(PHP_VERSION)
        ->and(Artisan::output())->not->toContain('diagnostic-private-storage', 'doctor-worker', 'doctor-locks', base64_encode(str_repeat('d', 32)));
});

it('keeps storage, queues, locks, HTTP and extension factories untouched', function (): void {
    Http::preventStrayRequests();
    Storage::shouldReceive('disk')->never();
    Queue::shouldReceive('connection')->never();
    Queue::shouldReceive('push')->never();
    Cache::shouldReceive('store')->never();
    Cache::shouldReceive('lock')->never();
    app()->bind(ImageEncoder::class, static fn () => throw new RuntimeException('Encoder factory must not run'));
    app(MediaExtensionState::class)->scanners['private-customer-scanner'] = static fn () => throw new RuntimeException('Scanner factory must not run');
    app(MediaExtensionState::class)->adapters['private-customer-adapter'] = static fn () => throw new RuntimeException('Adapter factory must not run');
    config()->set('filament-flex-fields.media_capture.require_virus_scan', true);
    expect(Artisan::call('fff:media:doctor', ['--queued' => true, '--custom-encoder' => true, '--json' => true]))->toBe(0)
        ->and(Artisan::output())->not->toContain('private-customer');
});

it('does not leak hostile names, paths, keys, credentials or URLs in a failure report', function (): void {
    $secret = 'sensitive-customer-token';
    config()->set([
        'filament-flex-fields.media_capture.disk' => $secret,
        'filament-flex-fields.media_capture.uploads.manifest_directory' => 'https://private.test/'.$secret,
        'app.key' => $secret,
        'queue.default' => $secret,
        'queue.connections.'.$secret => ['driver' => 'sync', 'password' => $secret],
    ]);
    expect(Artisan::call('fff:media:doctor', ['--queued' => true, '--custom-encoder' => true, '--connection' => $secret, '--json' => true]))->toBe(1);
    $output = Artisan::output();
    $report = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    expect($report['configurationValid'])->toBeFalse()
        ->and($report['counts']['failed'])->toBeGreaterThanOrEqual(4)
        ->and($output)->not->toContain($secret, 'private.test', '/diagnostic-private-storage');
});

it('prints readable effects and next steps without claiming the installation is operationally healthy', function (): void {
    expect(Artisan::call('fff:media:doctor'))->toBe(0)
        ->and(Artisan::output())->toContain('Operational verification is still required.', 'UNVERIFIED', 'Next step:');
});

it('rejects invalid command options without echoing untrusted input', function (array $options): void {
    expect(Artisan::call('fff:media:doctor', $options))->toBe(2)
        ->and(Artisan::output())->not->toContain('sensitive-customer-token');
})->with([
    [['--timeout' => 'sensitive-customer-token']],
    [['--queued' => true, '--timeout' => '0']],
    [['--queued' => true, '--timeout' => '3601']],
    [['--connection' => 'sensitive-customer-token']],
    [['--images' => true, '--format' => ['sensitive-customer-token']]],
    [['--images' => true, '--format' => ['webp', 'webp']]],
    [['--custom-encoder' => true]],
    [['--queue' => 'sensitive-customer-token']],
    [['--queued' => true, '--queue' => "sensitive-customer-token\n"]],
]);

it('keeps unavailable optional image checks out of the basic disk profile', function (): void {
    $report = app(MediaConfigurationDoctor::class)->inspect(new MediaDoctorOptions)->toArray();
    expect($report['configurationValid'])->toBeTrue()
        ->and($report['profile']['images'])->toBeFalse()
        ->and(array_filter($report['checks'], fn ($row) => str_starts_with($row['code'], 'gd_') || str_starts_with($row['code'], 'conversion_queue')))->toBe([]);
});

it('accepts a custom conversion queue without exporting its private name', function (): void {
    expect(Artisan::call('fff:media:doctor', ['--queued' => true, '--custom-encoder' => true, '--queue' => 'private-customer-jobs', '--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);
    expect($report['profile']['queueName'])->toBe('explicit')
        ->and(Artisan::output())->not->toContain('private-customer-jobs');
});
