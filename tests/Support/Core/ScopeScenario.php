<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Core;

use Bjanczak\FilamentFlexFields\Support\Enterprise\Access\FieldAccessState;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Access\TenantFieldPackState;
use Bjanczak\FilamentFlexFields\Support\Enterprise\TenantFieldPacks;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\FieldMounted;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingOs;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureTenantDiskResolver;
use Bjanczak\FilamentFlexFields\Support\Runtime\ScopedRenderCache;
use RuntimeException;

final class ScopeScenario
{
    public static function run(int $iteration): void
    {
        $cache = app(ScopedRenderCache::class);
        $context = app(TenantContext::class);
        $access = app(FieldAccessState::class);
        $telemetry = app(ActivityDispatcher::class);
        $packs = app(TenantFieldPackState::class);
        if ($context->id !== 0 || $cache->entries('worker') !== [] || $access->rules !== []
            || $context->activity !== null || $packs->packs !== []
            || FlexFieldStylesheetQueue::registered() !== []
            || MediaCaptureTenantDiskResolver::shouldApplyDirectoryPrefix()) {
            throw new RuntimeException('Request/job data leaked into a new worker scope.');
        }

        $context->id = $iteration % 7 + 1;
        $payload = str_repeat((string) $context->id, 8192);
        TenantFieldPacks::registerPack('tenant', ['field-'.$context->id]);
        if (TenantFieldPacks::packFor('tenant') !== ['field-'.$context->id]) {
            throw new RuntimeException('The current tenant policy was not applied.');
        }
        MediaCaptureTenantDiskResolver::registerDirectoryPrefixResolver(static fn (): string => $payload);
        $access->rules = ['tenant' => []];
        FlexFieldStylesheetQueue::enqueue('tenant-'.$context->id);
        $cache->remember('worker', 'same-record', static fn (): string => $payload);
        if ($cache->remember('worker', 'same-record', static fn (): string => 'wrong') !== $payload) {
            throw new RuntimeException('Render memoization lost its owner value.');
        }
        $provider = GeocodingOs::provider();
        $transcriber = MediaCaptureOs::transcriptionInterface();
        if (! $provider instanceof TenantProvider || $provider->tenant !== $context->id
            || $provider !== GeocodingOs::provider() || $transcriber !== MediaCaptureOs::transcriptionInterface()
            || $transcriber?->transcribe('local', 'probe') !== (string) $context->id) {
            throw new RuntimeException('A boot-registered factory reused a previous tenant provider.');
        }
        $telemetry->dispatch(new FieldMounted('tenant-'.$context->id, 'worker'));
        if ($context->activity !== 'tenant-'.$context->id) {
            throw new RuntimeException('The Laravel listener resolved a previous tenant scope.');
        }
        app(WorkerReport::class)->observe([$context, $cache, $access, $packs, $telemetry, $provider, $transcriber]);
        // Recovery must reset state even after a failing request/job.
        if ($iteration % 97 === 0) {
            throw new ExpectedScopeFailure('expected scope failure');
        }
    }
}
