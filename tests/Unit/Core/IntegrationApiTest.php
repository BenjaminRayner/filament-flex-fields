<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingOs;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingProviderContract;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\NullVoiceNoteTranscription;
use Bjanczak\FilamentFlexFields\Support\Media\VoiceNoteTranscriptionInterface;

it('retains a failed transcription initialization as an error until configuration is repaired', function (): void {
    config()->set('filament-flex-fields.media_capture.transcription', stdClass::class);
    for ($attempt = 0; $attempt < 2; $attempt++) {
        expect(fn () => MediaCaptureOs::transcriptionInterface())->toThrow(InvalidArgumentException::class);
    }
    config()->set('filament-flex-fields.media_capture.transcription', null);
    expect(MediaCaptureOs::transcriptionInterface())->toBeInstanceOf(VoiceNoteTranscriptionInterface::class);
});

it('resolves a geocoding factory once per scope and permits explicit instance replacement', function (): void {
    $calls = 0;
    $factory = function () use (&$calls): GeocodingProviderContract {
        $calls++;

        return new class implements GeocodingProviderContract
        {
            public function search(array $options): array
            {
                return ['features' => [], 'error' => null];
            }

            public function reverse(array $options): array
            {
                return ['feature' => null, 'error' => null];
            }
        };
    };
    GeocodingOs::registerProviderFactory($factory);
    $first = GeocodingOs::provider();
    expect(GeocodingOs::provider())->toBe($first)->and($calls)->toBe(1);
    $replacement = $factory();
    GeocodingOs::registerProvider($replacement);
    expect(GeocodingOs::provider())->toBe($replacement);
    app()->forgetScopedInstances();
    GeocodingOs::registerProviderFactory($factory);
    expect(GeocodingOs::provider())->not->toBe($first)->and($calls)->toBe(3);
});

it('retries throwing and invalid factories without caching their failure', function (bool $geocoding): void {
    $calls = 0;
    $factory = function () use (&$calls): mixed {
        $calls++;
        if ($calls === 1) {
            throw new RuntimeException('initial construction failure');
        }

        return new stdClass;
    };
    $geocoding ? GeocodingOs::registerProviderFactory($factory) : MediaCaptureOs::registerTranscriptionFactory($factory);
    $resolve = $geocoding ? fn () => GeocodingOs::provider() : fn () => MediaCaptureOs::transcriptionInterface();
    expect($resolve)->toThrow(RuntimeException::class, 'initial construction failure')
        ->and($resolve)->toThrow(InvalidArgumentException::class, 'factory must return')
        ->and($calls)->toBe(2);
})->with([true, false]);

it('resolves transcription factories once and preserves explicit disablement', function (): void {
    $calls = 0;
    MediaCaptureOs::registerTranscriptionFactory(function () use (&$calls): VoiceNoteTranscriptionInterface {
        $calls++;

        return new NullVoiceNoteTranscription;
    });
    $provider = MediaCaptureOs::transcriptionInterface();
    MediaCaptureOs::bootFromConfig();
    expect(MediaCaptureOs::transcriptionInterface())->toBe($provider)->and($calls)->toBe(1);
    MediaCaptureOs::registerTranscriptionInterface(null);
    MediaCaptureOs::registerSignedUploadUrlResolver(null);
    MediaCaptureOs::bootFromConfig();
    expect(MediaCaptureOs::transcriptionInterface())->toBeNull()
        ->and(MediaCaptureOs::signedUploadUrlResolver())->toBeNull();
});

it('normalizes callable objects and rejects malformed media configuration consistently', function (): void {
    $scanner = new class
    {
        public function __invoke(string $path): bool
        {
            return $path === 'accepted';
        }
    };
    MediaCaptureOs::registerVirusScanCallback($scanner);
    expect(MediaCaptureOs::passesVirusScan('accepted'))->toBeTrue()
        ->and(MediaCaptureOs::passesVirusScan('rejected'))->toBeFalse();
    config()->set('filament-flex-fields.media_capture', 'invalid');
    expect(fn () => MediaCaptureOs::bootFromConfig())->toThrow(InvalidArgumentException::class)
        ->and(fn () => MediaCaptureOs::transcriptionInterface())->toThrow(InvalidArgumentException::class);
});
