<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Admin\HoldConfirmEnterprise;
use Bjanczak\FilamentFlexFields\Support\Compliance\CompliancePack;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureTenantDiskResolver;
use Bjanczak\FilamentFlexFields\Support\Runtime\ScopedRenderCache;
use Bjanczak\FilamentFlexFields\Support\WhisperRuntime;

it('isolates every column cache across worker scopes', function (string $cache): void {
    expect($cache::remember('record-one', fn (): string => 'tenant one'))->toBe('tenant one')
        ->and($cache::remember('record-one', fn (): string => 'changed'))->toBe('tenant one');
    app()->forgetScopedInstances();
    expect($cache::entries())->toBe([])
        ->and($cache::remember('record-one', fn (): string => 'tenant two'))->toBe('tenant two');
})->with(array_map(fn (string $name): string => 'Bjanczak\\FilamentFlexFields\\Support\\'.$name, [
    'IconColumnRenderCache', 'ProgressColumnRenderCache', 'MapPinColumnRenderCache',
    'RatingColumnRenderCache', 'SignaturePreviewColumnRenderCache', 'StatusChipColumnRenderCache',
    'UserColumnRenderCache', 'UserColumnSharedStackCache',
]));

it('bounds render memoization per owner and caches nullable results', function (): void {
    $cache = app(ScopedRenderCache::class);
    $calls = 0;
    $null = function () use (&$calls): null {
        $calls++;

        return null;
    };
    $cache->remember('one', 'nullable', $null);
    $cache->remember('one', 'nullable', $null);
    expect($calls)->toBe(1);
    for ($index = 0; $index < 1000; $index++) {
        $cache->remember('one', 'record-'.$index, fn (): string => 'value');
    }
    $cache->remember('two', 'record', fn (): string => 'independent');
    expect($cache->entries('one'))->toHaveCount(512)
        ->and($cache->entries('two'))->toBe(['record' => 'independent']);
    $cache->flush('one');
    expect($cache->entries('one'))->toBe([])
        ->and($cache->entries('two'))->toHaveCount(1);
});

it('does not retain tenant disks, admin policies, compliance overrides or runtime fakes', function (): void {
    $baselineDisk = MediaCaptureTenantDiskResolver::resolveDisk('local');
    MediaCaptureTenantDiskResolver::registerDiskResolver(fn (): string => 'tenant-one');
    MediaCaptureTenantDiskResolver::registerDirectoryPrefixResolver(fn (): string => 'tenant-one');
    HoldConfirmEnterprise::requiresAuditReason(true);
    HoldConfirmEnterprise::bulkHoldMs(4200);
    CompliancePack::mark('select-field', CompliancePack::STATUS_FAIL);
    WhisperRuntime::usePublicDirectory('/tenant-one/whisper');
    WhisperRuntime::fakeInstalled(true);
    expect(MediaCaptureTenantDiskResolver::resolveDisk('local'))->toBe('tenant-one')
        ->and(MediaCaptureTenantDiskResolver::shouldApplyDirectoryPrefix())->toBeTrue()
        ->and(WhisperRuntime::isInstalled())->toBeTrue();

    app()->forgetScopedInstances();

    expect(MediaCaptureTenantDiskResolver::resolveDisk('local'))->toBe($baselineDisk)
        ->and(MediaCaptureTenantDiskResolver::shouldApplyDirectoryPrefix())->toBeFalse()
        ->and(HoldConfirmEnterprise::requiresAuditReason())->toBeFalse()
        ->and(HoldConfirmEnterprise::bulkHoldMs())->toBe(HoldConfirmEnterprise::DEFAULT_BULK_HOLD_MS)
        ->and(CompliancePack::aaMatrix()['select-field'])->toBe(CompliancePack::STATUS_PASS)
        ->and(WhisperRuntime::publicDirectory())->not->toBe('/tenant-one/whisper');
});
