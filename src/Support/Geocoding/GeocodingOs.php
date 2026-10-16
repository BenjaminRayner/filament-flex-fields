<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Geocoding;

use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Closure;
use InvalidArgumentException;

final class GeocodingOs
{
    /**
     * @param  array<string, mixed>|null  $config
     */
    public static function bootFromConfig(?array $config = null): void
    {
        $config ??= config('filament-flex-fields.geocoding', []);

        if (! is_array($config)) {
            throw new InvalidArgumentException('Geocoding configuration must be an array.');
        }

        $driver = (string) ($config['driver'] ?? 'mapbox');

        $inner = match ($driver) {
            'mapbox' => self::buildMapboxProvider($config),
            default => throw new InvalidArgumentException('Unknown geocoding driver. Register a GeocodingProviderContract instead of silently falling back.'),
        };

        if (($config['circuit_breaker']['enabled'] ?? true) === true) {
            $scope = $config['circuit_breaker']['cache_scope'] ?? null;
            if ($scope === null && $inner instanceof MapboxGeocodingProvider) {
                $scope = hash('sha256', (string) config('filament-flex-fields.mapbox.access_token'));
            }
            self::state()->provider = new CircuitBreakerGeocodingProvider(
                $inner,
                (int) ($config['circuit_breaker']['failure_threshold'] ?? 5),
                (int) ($config['circuit_breaker']['open_seconds'] ?? 60),
                $scope,
                (int) ($config['circuit_breaker']['probe_seconds'] ?? 60),
            );

            return;
        }

        self::state()->provider = $inner;
    }

    public static function provider(): GeocodingProviderContract
    {
        if (self::state()->provider === null) {
            $factory = self::state()->factory;
            if ($factory !== null) {
                $provider = $factory();
                if (! $provider instanceof GeocodingProviderContract) {
                    throw new InvalidArgumentException('Geocoding factory must return GeocodingProviderContract.');
                }
                self::state()->provider = $provider;
            } else {
                self::bootFromConfig();
            }
        }

        return self::state()->provider ?? throw new \LogicException('Geocoding provider was not initialized.');
    }

    public static function registerProvider(?GeocodingProviderContract $provider): void
    {
        RequestState::configure(GeocodingState::class, static function (GeocodingState $state) use ($provider): void {
            $state->provider = $provider;
            $state->factory = null;
        });
    }

    /** @param (Closure(): GeocodingProviderContract)|null $factory */
    public static function registerProviderFactory(?Closure $factory): void
    {
        RequestState::configure(GeocodingState::class, static function (GeocodingState $state) use ($factory): void {
            $state->provider = null;
            $state->factory = $factory;
        });
    }

    public static function reset(): void
    {
        self::state()->provider = null;
        self::state()->factory = null;
    }

    private static function state(): GeocodingState
    {
        return RequestState::resolve(GeocodingState::class);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function buildMapboxProvider(array $config): GeocodingProviderContract
    {
        $class = $config['mapbox_provider'] ?? MapboxGeocodingProvider::class;

        if (! is_string($class) || ! is_subclass_of($class, GeocodingProviderContract::class)) {
            throw new InvalidArgumentException('Configured geocoding provider must implement GeocodingProviderContract.');
        }

        return app($class);
    }
}
