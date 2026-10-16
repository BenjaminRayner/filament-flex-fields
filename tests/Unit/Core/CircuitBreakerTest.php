<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Geocoding\CircuitBreakerGeocodingProvider;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingOs;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingProviderContract;
use Bjanczak\FilamentFlexFields\Support\Media\CircuitBreakerTranscriptionProvider;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\VoiceNoteTranscriptionInterface;
use Bjanczak\FilamentFlexFields\Support\Runtime\CircuitBreaker;
use Bjanczak\FilamentFlexFields\Support\Runtime\CircuitOpenException;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

afterEach(function (): void {
    $this->travelBack();
});

it('isolates circuit policy and identity while sharing equal upstream identities', function (): void {
    $first = new CircuitBreaker('tenant-one', 1, 30);
    expect(fn () => $first->run(fn () => throw new RuntimeException('secret')))->toThrow(RuntimeException::class);
    expect(fn () => (new CircuitBreaker('tenant-one', 1, 30))->run(fn () => true))->toThrow(CircuitOpenException::class);
    expect((new CircuitBreaker('tenant-two', 1, 30))->run(fn () => true))->toBeTrue()
        ->and((new CircuitBreaker('tenant-one', 3, 30))->run(fn () => true))->toBeTrue();
});

it('does not allow a stale successful request to close a newer failed circuit', function (): void {
    $circuit = new CircuitBreaker('late-success', 1, 30);
    expect($circuit->run(function () use ($circuit): string {
        try {
            $circuit->run(fn () => throw new RuntimeException('failure'));
        } catch (RuntimeException) {
        }

        return 'old success';
    }))->toBe('old success');

    expect(fn () => $circuit->run(fn () => true))->toThrow(CircuitOpenException::class);
});

it('admits exactly one recovery probe and closes only after its success', function (): void {
    $circuit = new CircuitBreaker('probe', 1, 30);
    expect(fn () => $circuit->run(fn () => throw new RuntimeException('failure')))->toThrow(RuntimeException::class);
    $this->travel(31)->seconds();

    expect($circuit->run(function () use ($circuit): string {
        expect(fn () => $circuit->run(fn () => 'second probe'))->toThrow(CircuitOpenException::class);

        return 'recovered';
    }))->toBe('recovered')
        ->and($circuit->run(fn () => 'healthy'))->toBe('healthy');
});

it('reopens after a failed probe and retains its recovery deadline', function (): void {
    $circuit = new CircuitBreaker('failed-probe', 1, 30);
    expect(fn () => $circuit->run(fn () => throw new RuntimeException('failure')))->toThrow(RuntimeException::class);
    $this->travel(31)->seconds();
    expect(fn () => $circuit->run(fn () => throw new RuntimeException('still down')))->toThrow(RuntimeException::class)
        ->and(fn () => $circuit->run(fn () => true))->toThrow(CircuitOpenException::class);
});

it('ignores expired probe results after another probe has recovered', function (): void {
    $circuit = new CircuitBreaker('expired-probe', 1, 1, 1);
    expect(fn () => $circuit->run(fn () => throw new RuntimeException('failure')))->toThrow(RuntimeException::class);
    $this->travel(2)->seconds();
    expect(fn () => $circuit->run(function () use ($circuit): never {
        $this->travel(2)->seconds();
        expect($circuit->run(fn () => 'recovered'))->toBe('recovered');

        throw new RuntimeException('old probe');
    }))->toThrow(RuntimeException::class, 'old probe');
    expect($circuit->run(fn () => 'healthy'))->toBe('healthy');
});

it('resets consecutive failures after success and treats returned errors as failures', function (): void {
    $circuit = new CircuitBreaker('structured', 2, 30);
    $failure = fn (): array => ['error' => 'unavailable'];
    $failed = fn (array $result): bool => $result['error'] !== null;
    $circuit->run($failure, $failed);
    $circuit->run(fn (): array => ['error' => null], $failed);
    $circuit->run($failure, $failed);
    expect($circuit->run($failure, $failed))->toBe(['error' => 'unavailable'])
        ->and(fn () => $circuit->run($failure, $failed))->toThrow(CircuitOpenException::class);
});

it('counts structured geocoding errors without exposing thrown messages', function (): void {
    $provider = new class implements GeocodingProviderContract
    {
        public int $calls = 0;

        public function search(array $options): array
        {
            $this->calls++;

            return ['features' => [], 'error' => 'unavailable'];
        }

        public function reverse(array $options): array
        {
            throw new RuntimeException('private upstream credentials');
        }
    };
    $circuit = new CircuitBreakerGeocodingProvider($provider, 1, 30, 'tenant-one');
    $circuit->search([]);
    expect($circuit->search([])['error'])->toBe(__('filament-flex-fields::default.geocoding.circuit_open'))
        ->and($provider->calls)->toBe(1);
    $other = new CircuitBreakerGeocodingProvider($provider, 1, 30, 'tenant-two');
    expect($other->reverse([])['error'])->toBe(__('filament-flex-fields::default.geocoding.failed'));
});

it('rejects invalid circuit budgets before cache or provider work', function (int $threshold, int $seconds, int $probe): void {
    expect(fn () => new CircuitBreaker('limits', $threshold, $seconds, $probe))->toThrow(InvalidArgumentException::class);
})->with([[0, 30, 60], [1, 0, 60], [1, 30, 0], [-1, 30, 60]]);

it('does not call an upstream when its circuit state cannot be persisted', function (): void {
    Cache::swap(new Repository(new class extends ArrayStore
    {
        public function put($key, $value, $seconds): bool
        {
            return false;
        }
    }));
    $calls = 0;
    expect(fn () => (new CircuitBreaker('failed-cache', 1, 30))->run(function () use (&$calls): bool {
        $calls++;

        return true;
    }))->toThrow(RuntimeException::class, 'Cannot persist')
        ->and($calls)->toBe(0);
});

it('isolates built-in Mapbox circuit health by credentials without an explicit scope', function (): void {
    Http::fake(['*' => Http::response([], 503)]);
    config()->set('filament-flex-fields.mapbox.access_token', 'account-one');
    $config = ['driver' => 'mapbox', 'circuit_breaker' => ['failure_threshold' => 1, 'open_seconds' => 30]];
    GeocodingOs::bootFromConfig($config);
    GeocodingOs::provider()->search(['query' => 'Paris']);
    expect(GeocodingOs::provider()->search(['query' => 'Paris'])['error'])
        ->toBe(__('filament-flex-fields::default.geocoding.circuit_open'));

    config()->set('filament-flex-fields.mapbox.access_token', 'account-two');
    app()->forgetScopedInstances();
    GeocodingOs::bootFromConfig($config);
    expect(GeocodingOs::provider()->search(['query' => 'Paris'])['error'])
        ->toBe(__('filament-flex-fields::default.geocoding.failed'));
    Http::assertSentCount(2);
});

it('rejects invalid configured provider classes instead of silently selecting another implementation', function (mixed $class): void {
    GeocodingOs::reset();
    MediaCaptureOs::resetRuntimeState();
    expect(fn () => GeocodingOs::bootFromConfig(['mapbox_provider' => $class]))
        ->toThrow(InvalidArgumentException::class, 'GeocodingProviderContract')
        ->and(fn () => MediaCaptureOs::bootFromConfig(['transcription' => $class]))
        ->toThrow(InvalidArgumentException::class, 'VoiceNoteTranscriptionInterface');
})->with([stdClass::class, 'MissingProviderClass', 42]);

it('keeps null transcription results healthy and recovers after a failed provider', function (): void {
    $provider = new class implements VoiceNoteTranscriptionInterface
    {
        public int $calls = 0;

        public bool $fail = false;

        public function transcribe(string $disk, string $path, array $context = []): ?string
        {
            $this->calls++;
            if ($this->fail) {
                throw new RuntimeException('private provider error');
            }

            return null;
        }
    };
    $circuit = new CircuitBreakerTranscriptionProvider($provider, 1, 30, 'nullable-transcription');
    $circuit->transcribe('local', 'one');
    $circuit->transcribe('local', 'two');
    expect($provider->calls)->toBe(2);
    $provider->fail = true;
    $circuit->transcribe('local', 'three');
    $circuit->transcribe('local', 'four');
    expect($provider->calls)->toBe(3);
    $this->travel(31)->seconds();
    $provider->fail = false;
    expect($circuit->transcribe('local', 'five'))->toBeNull()
        ->and($provider->calls)->toBe(4);
});
