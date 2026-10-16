<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VoiceNoteRecorderField;
use Bjanczak\FilamentFlexFields\Support\Geocoding\CircuitBreakerGeocodingProvider;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingOs;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingProviderContract;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureTenantDiskResolver;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    GeocodingOs::reset();
    Cache::flush();
});

it('opens the geocoding circuit breaker after repeated failures', function () {
    $failing = new class implements GeocodingProviderContract
    {
        public function search(array $options): array
        {
            throw new RuntimeException('upstream down');
        }

        public function reverse(array $options): array
        {
            throw new RuntimeException('upstream down');
        }
    };

    GeocodingOs::registerProvider(new CircuitBreakerGeocodingProvider($failing, 2, 30));

    expect(GeocodingOs::provider()->search(['query' => 'Paris'])['error'])->toBe(__('filament-flex-fields::default.geocoding.failed'))
        ->and(GeocodingOs::provider()->search(['query' => 'Paris'])['error'])->toBe(__('filament-flex-fields::default.geocoding.failed'))
        ->and(GeocodingOs::provider()->search(['query' => 'Paris'])['error'])->toBe(__('filament-flex-fields::default.geocoding.circuit_open'));
});

it('registers capture retention schedule when enabled', function () {
    config(['filament-flex-fields.media_capture.retention.schedule_enabled' => true]);

    $schedule = app(Schedule::class);

    expect(collect($schedule->events())->contains(
        fn ($event) => str_contains((string) ($event->command ?? ''), 'flex-fields:prune-capture-media'),
    ))->toBeTrue();
});

it('resolves tenant scoped upload directories from config prefix', function () {
    config(['filament-flex-fields.media_capture.tenant.directory_prefix' => 'tenant-42']);

    $directory = MediaCaptureTenantDiskResolver::resolveDirectory('voice-notes');

    expect($directory)->toStartWith('tenant-42/voice-notes/');
});

it('falls back to storeMetadataIn for voice note waveform path', function () {
    $field = VoiceNoteRecorderField::make('voice')
        ->storeMetadataIn('voice_meta');

    expect($field->getStoreWaveformInPath())->toBe('voice_meta');
});
