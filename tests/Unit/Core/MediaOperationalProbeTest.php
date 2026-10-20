<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaImageProbe;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaLockProbe;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaOperationalProbe;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaProbeOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaProbeReport;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaStorageProbe;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

it('requires explicit write acknowledgement and at least one target before any work', function (array $options): void {
    expect(fn () => new MediaProbeOptions(...$options))->toThrow(InvalidArgumentException::class);
})->with([
    [[]], [['disk' => 'private']], [['lockStore' => 'file']], [['formats' => ['webp']]],
    [['allowWrites' => true]], [['allowWrites' => true, 'disk' => '']],
    [['allowWrites' => true, 'lockStore' => "bad\nname"]],
    [['allowWrites' => true, 'disk' => str_repeat('a', 129)]],
    [['allowWrites' => true, 'formats' => ['webp', 'webp']]],
    [['allowWrites' => true, 'formats' => ['gif']]],
    [['allowWrites' => true, 'formats' => ['png', 'jpg', 'webp', 'avif', 'png']]],
    [['allowWrites' => true, 'formats' => [2 => 'png']]],
    [['allowWrites' => true, 'formats' => [new stdClass]]],
]);

it('detaches format references and never invokes string conversion on hostile input', function (): void {
    $format = 'webp';
    $options = new MediaProbeOptions(allowWrites: true, formats: [&$format]);
    $format = 'private-customer-token';
    expect($options->formats)->toBe(['webp']);
    $value = new class
    {
        public function __toString(): string
        {
            throw new RuntimeException('Must not execute untrusted conversion.');
        }
    };
    expect(fn () => new MediaProbeOptions(allowWrites: true, formats: [$value]))->toThrow(InvalidArgumentException::class);
});

it('roundtrips a real local object, checks private metadata, deletes only its exact random path and preserves other data', function (): void {
    Storage::fake('probe-private');
    Storage::disk('probe-private')->put('originals/customer.png', 'customer bytes');
    Storage::disk('probe-private')->put('.fff-diagnostics/other.probe', 'another run');
    $options = new MediaProbeOptions(allowWrites: true, disk: 'probe-private');
    $first = app(MediaOperationalProbe::class)->inspect($options)->toArray();
    $second = app(MediaOperationalProbe::class)->inspect($options)->toArray();
    expect($first['passed'])->toBeTrue()->and($second['passed'])->toBeTrue()
        ->and($first['runId'])->toMatch('/^[a-f0-9]{32}$/D')->not->toBe($second['runId'])
        ->and($first['counts'])->toBe(['passed' => 5, 'failed' => 0])
        ->and($first['cleanupRequired'])->toBeFalse()
        ->and(Storage::disk('probe-private')->allFiles())->toBe(['.fff-diagnostics/other.probe', 'originals/customer.png'])
        ->and(Storage::disk('probe-private')->get('originals/customer.png'))->toBe('customer bytes');
});

it('detects storage failures and cleans partial writes without listing or deleting directories', function (string $mode, string $expectedCode, bool $cleanupRequired): void {
    $disk = Mockery::mock(Filesystem::class);
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('disk')->once()->with('private-secret')->andReturn($disk);
    $path = null;
    $payload = null;
    $disk->shouldReceive('put')->once()->andReturnUsing(function (string $key, string $bytes, array $options) use ($mode, &$path, &$payload): bool {
        expect($key)->toMatch('~^\.fff-diagnostics/[a-f0-9]{32}\.probe$~D')
            ->and(strlen($bytes))->toBeLessThan(128)->and($options)->toBe(['visibility' => 'private']);
        $path = $key;
        $payload = $bytes;
        if ($mode === 'write-throw') {
            throw new RuntimeException('https://private.test/secret-credentials');
        }

        return $mode !== 'write-false';
    });
    if (! str_starts_with($mode, 'write-')) {
        $disk->shouldReceive('readStream')->once()->andReturnUsing(function (string $key) use ($mode, &$path, &$payload) {
            expect($key)->toBe($path);
            if ($mode === 'read-throw') {
                throw new RuntimeException('secret-credentials');
            }
            if ($mode === 'read-false') {
                return false;
            }
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, match ($mode) {
                'read-corrupt' => 'wrong bytes', 'read-oversize' => $payload.str_repeat('x', 1048576), default => $payload,
            });
            rewind($stream);

            return $stream;
        });
        $visibility = $disk->shouldReceive('getVisibility')->once();
        if ($mode === 'visibility-throw') {
            $visibility->andThrow(new RuntimeException('secret-credentials'));
        } else {
            $visibility->andReturn($mode === 'visibility-public' ? 'public' : 'private');
        }
    }
    $disk->shouldReceive('delete')->once()->andReturnUsing(function (string $key) use ($mode, &$path): bool {
        expect($key)->toBe($path);
        if ($mode === 'delete-throw') {
            throw new RuntimeException('secret-credentials');
        }

        return $mode !== 'delete-false';
    });
    if (! in_array($mode, ['delete-throw', 'delete-false'], true)) {
        $disk->shouldReceive('exists')->once()->andReturn($mode === 'delete-ineffective');
    }
    $disk->shouldNotReceive('allFiles', 'files', 'deleteDirectory');
    $report = new MediaProbeReport(new MediaProbeOptions(allowWrites: true, disk: 'private-secret'));
    (new MediaStorageProbe($factory))->inspect('private-secret', $report);
    $result = $report->toArray();
    $statuses = array_column($result['checks'], 'status', 'code');
    expect($result['passed'])->toBeFalse()->and($statuses[$expectedCode])->toBe('failed')
        ->and($result['cleanupRequired'])->toBe($cleanupRequired)
        ->and(json_encode($result))->not->toContain('private-secret', 'secret-credentials', 'private.test');
})->with([
    ['write-false', 'storage_write', false], ['write-throw', 'storage_write', false],
    ['read-false', 'storage_read', false], ['read-throw', 'storage_read', false],
    ['read-corrupt', 'storage_read', false], ['read-oversize', 'storage_read', false],
    ['visibility-public', 'storage_visibility', false], ['visibility-throw', 'storage_visibility', false],
    ['delete-false', 'storage_cleanup', true], ['delete-throw', 'storage_cleanup', true], ['delete-ineffective', 'storage_cleanup', true],
]);

it('reports a disk construction failure without touching any storage path', function (): void {
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('disk')->once()->andThrow(new RuntimeException('private-customer-token'));
    $report = new MediaProbeReport(new MediaProbeOptions(allowWrites: true, disk: 'private-customer-token'));
    (new MediaStorageProbe($factory))->inspect('private-customer-token', $report);
    expect($report->toArray()['counts'])->toBe(['passed' => 0, 'failed' => 1])
        ->and($report->toArray()['cleanupRequired'])->toBeFalse()
        ->and(json_encode($report->toArray()))->not->toContain('private-customer-token');
});

it('uses the real file lock store and leaves unrelated locks intact', function (): void {
    $directory = sys_get_temp_dir().'/fff-lock-test-'.bin2hex(random_bytes(8));
    config()->set('cache.stores.probe-file', ['driver' => 'file', 'path' => $directory, 'lock_path' => $directory]);
    $other = Cache::store('probe-file')->lock('unrelated-job', 60);
    expect($other->get())->toBeTrue();
    try {
        $result = app(MediaOperationalProbe::class)->inspect(new MediaProbeOptions(allowWrites: true, lockStore: 'probe-file'))->toArray();
        expect($result['passed'])->toBeTrue()->and($result['counts'])->toBe(['passed' => 6, 'failed' => 0])
            ->and($result['operationallyVerified'])->toBeFalse()->and($result['cleanupRequired'])->toBeFalse()
            ->and(Cache::store('probe-file')->lock('unrelated-job', 60)->get())->toBeFalse();
    } finally {
        $other->release();
        app('files')->deleteDirectory($directory);
    }
});

it('reports broken lock contracts and only releases its own handles', function (string $mode, string $expected): void {
    $primary = Mockery::mock(Lock::class);
    $contender = Mockery::mock(Lock::class);
    $primaryGet = $primary->shouldReceive('get')->once();
    if ($mode === 'acquire-throw') {
        $primaryGet->andThrow(new RuntimeException('private-customer-token'));
    } else {
        $primaryGet->andReturn($mode !== 'acquire-false');
    }
    $primary->shouldNotReceive('forceRelease', 'block');
    $contender->shouldNotReceive('forceRelease', 'block');
    $primary->shouldReceive('release')->andReturn($mode !== 'release-false');
    $contender->shouldReceive('release')->andReturn($mode === 'foreign-release' || $mode === 'exclusion');
    if (! str_starts_with($mode, 'acquire-')) {
        $contenderGet = $contender->shouldReceive('get');
        if ($mode === 'contender-throw') {
            $contenderGet->andThrow(new RuntimeException('private-customer-token'));
        } else {
            $contenderGet->andReturn($mode === 'invalid-result' ? null : $mode === 'exclusion');
        }
    }
    $store = Mockery::mock(Store::class, LockProvider::class);
    $store->shouldReceive('lock')->withArgs(fn ($key, $ttl, $owner) => preg_match('/^fff:diagnostic:[a-f0-9]{32}$/D', $key) && $ttl === 60 && preg_match('/^[a-f0-9]{32}$/D', $owner))->twice()->andReturn($primary, $contender);
    $manager = Mockery::mock(CacheManager::class);
    $manager->shouldReceive('store')->once()->with('private-store')->andReturn(new Repository($store));
    $report = new MediaProbeReport(new MediaProbeOptions(allowWrites: true, lockStore: 'private-store'));
    (new MediaLockProbe($manager))->inspect('private-store', $report);
    $result = $report->toArray();
    expect(array_column($result['checks'], 'status', 'code')[$expected])->toBe('failed')
        ->and($result['passed'])->toBeFalse()
        ->and(json_encode($result))->not->toContain('private-store', 'private-customer-token');
})->with([
    ['acquire-false', 'lock_acquire'], ['acquire-throw', 'lock_acquire'],
    ['exclusion', 'lock_exclusion'], ['foreign-release', 'lock_ownership'],
    ['invalid-result', 'lock_exclusion'],
    ['contender-throw', 'lock_exclusion'], ['release-false', 'lock_reacquire'], ['reacquire-false', 'lock_reacquire'],
]);

it('rejects a cache backend without lock support and redacts driver construction failures', function (bool $throws): void {
    $manager = Mockery::mock(CacheManager::class);
    $expectation = $manager->shouldReceive('store')->once();
    if ($throws) {
        $expectation->andThrow(new RuntimeException('private-customer-token'));
    } else {
        $expectation->andReturn(new Repository(Mockery::mock(Store::class)));
    }
    $report = new MediaProbeReport(new MediaProbeOptions(allowWrites: true, lockStore: 'private-customer-token'));
    (new MediaLockProbe($manager))->inspect('private-customer-token', $report);
    expect($report->toArray()['checks'][0]['code'])->toBe('lock_resolution')
        ->and($report->toArray()['passed'])->toBeFalse()
        ->and(json_encode($report->toArray()))->not->toContain('private-customer-token');
})->with([false, true]);

it('reports a cleanup fault after successful lock acquisition and still cleans the other owned handle', function (bool $throws): void {
    $primary = Mockery::mock(Lock::class);
    $contender = Mockery::mock(Lock::class);
    $primary->shouldReceive('get')->once()->andReturn(true);
    $primary->shouldReceive('release')->twice()->andReturn(true, false);
    $contender->shouldReceive('get')->times(3)->andReturn(false, false, true);
    $release = $contender->shouldReceive('release')->twice();
    if ($throws) {
        $release->andReturnUsing(function (): bool {
            static $calls = 0;
            if (++$calls === 1) {
                return false;
            }
            throw new RuntimeException('private-cleanup-token');
        });
    } else {
        $release->andReturn(false);
    }
    $primary->shouldNotReceive('forceRelease', 'block');
    $contender->shouldNotReceive('forceRelease', 'block');
    $store = Mockery::mock(Store::class, LockProvider::class);
    $store->shouldReceive('lock')->twice()->andReturn($primary, $contender);
    $manager = Mockery::mock(CacheManager::class);
    $manager->shouldReceive('store')->once()->andReturn(new Repository($store));
    $report = new MediaProbeReport(new MediaProbeOptions(allowWrites: true, lockStore: 'private'));
    (new MediaLockProbe($manager))->inspect('private', $report);
    $result = $report->toArray();
    expect(array_column($result['checks'], 'status', 'code'))->toMatchArray(['lock_reacquire' => 'passed', 'lock_cleanup' => 'failed'])
        ->and($result['cleanupRequired'])->toBeTrue()->and($result['passed'])->toBeFalse()
        ->and(json_encode($result))->not->toContain('private-cleanup-token');
})->with([false, true]);

it('converts synthetic input with the real bundled encoder and never resolves a custom encoder binding', function (): void {
    if (! function_exists('imagewebp') || ! function_exists('imagepng') || ! function_exists('imagejpeg')) {
        $this->markTestSkipped('Requires the bundled GD PNG/JPEG/WebP profile.');
    }
    app()->bind(ImageEncoder::class, fn () => throw new RuntimeException('Custom encoder must not run'));
    $report = new MediaProbeReport(new MediaProbeOptions(allowWrites: true, formats: ['jpg', 'png', 'webp']));
    (new MediaImageProbe)->inspect(['jpg', 'png', 'webp'], $report);
    expect($report->toArray()['passed'])->toBeTrue()->and($report->toArray()['counts'])->toBe(['passed' => 5, 'failed' => 0]);
});

it('reports the safe run identity before any selected backend is resolved', function (): void {
    $started = null;
    Storage::fake('probe-private');
    $report = app(MediaOperationalProbe::class)->inspect(new MediaProbeOptions(allowWrites: true, disk: 'probe-private'), function (string $id) use (&$started): void {
        $started = $id;
        expect(Storage::disk('probe-private')->allFiles())->toBe([]);
    })->toArray();
    expect($started)->toBe($report['runId'])->and($report['passed'])->toBeTrue();
});

it('keeps the exported report closed, bounded and distinct from a deployment certificate', function (): void {
    $report = new MediaProbeReport(new MediaProbeOptions(allowWrites: true, disk: 'private-customer-token'));
    expect($report->toArray()['passed'])->toBeFalse()
        ->and(fn () => $report->add('private-customer-token', true))->toThrow(LogicException::class);
    $report->check('storage_read', fn () => throw new RuntimeException('private-customer-token'));
    for ($i = 0; $i < 31; $i++) {
        $report->add('storage_read', true);
    }
    expect(fn () => $report->add('storage_read', true))->toThrow(LogicException::class)
        ->and($report->toArray()['operationallyVerified'])->toBeFalse()
        ->and($report->toArray()['readOnly'])->toBeFalse()
        ->and($report->toArray()['profile']['disk'])->toBe('explicit')
        ->and(json_encode($report->toArray()))->not->toContain('private-customer-token');
});
