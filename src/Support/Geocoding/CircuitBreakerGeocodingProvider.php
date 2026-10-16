<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Geocoding;

use Bjanczak\FilamentFlexFields\Support\Runtime\CircuitBreaker;
use Bjanczak\FilamentFlexFields\Support\Runtime\CircuitOpenException;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Throwable;

/** Geocoding result semantics over the shared upstream resilience policy. */
final class CircuitBreakerGeocodingProvider implements GeocodingProviderContract
{
    private readonly CircuitBreaker $circuit;

    public function __construct(
        private readonly GeocodingProviderContract $provider,
        int $failureThreshold = 5,
        int $openSeconds = 60,
        ?string $cacheScope = null,
        int $probeSeconds = 60,
    ) {
        $this->circuit = new CircuitBreaker(
            'geocoding:'.$provider::class.':'.($cacheScope ?? 'default'),
            $failureThreshold, $openSeconds, $probeSeconds,
        );
    }

    /** @param array<string, mixed> $options
     * @return array{features: list<array<string, mixed>>, error: string|null}
     */
    public function search(array $options): array
    {
        try {
            return $this->circuit->run(fn (): array => $this->provider->search($options),
                static fn (array $result): bool => $result['error'] !== null);
        } catch (CircuitOpenException) {
            return ['features' => [], 'error' => Translations::get('filament-flex-fields::default.geocoding.circuit_open')];
        } catch (Throwable) {
            return ['features' => [], 'error' => Translations::get('filament-flex-fields::default.geocoding.failed')];
        }
    }

    /** @param array<string, mixed> $options
     * @return array{feature: array<string, mixed>|null, error: string|null}
     */
    public function reverse(array $options): array
    {
        try {
            return $this->circuit->run(fn (): array => $this->provider->reverse($options),
                static fn (array $result): bool => $result['error'] !== null);
        } catch (CircuitOpenException) {
            return ['feature' => null, 'error' => Translations::get('filament-flex-fields::default.geocoding.circuit_open')];
        } catch (Throwable) {
            return ['feature' => null, 'error' => Translations::get('filament-flex-fields::default.geocoding.failed')];
        }
    }
}
