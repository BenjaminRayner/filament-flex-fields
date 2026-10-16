<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Mapbox;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MapboxGeocodingClient
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{features: list<array<string, mixed>>, error: string|null}
     */
    public function search(array $options): array
    {
        $query = (string) $options['query'];
        $cacheKey = $this->cacheKey('search', $options);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($options, $query): array {
            $url = sprintf(
                'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json',
                rawurlencode($query),
            );

            $response = Http::timeout(12)->get($url, $this->queryParams($options, [
                'limit' => (int) ($options['limit'] ?? 6),
                'autocomplete' => ($options['autocomplete'] ?? true) ? 'true' : 'false',
            ]));

            return $this->applyAddressPolicy($this->normalizeResponse($response->throw()->json()), $options);
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{feature: array<string, mixed>|null, error: string|null}
     */
    public function reverse(array $options): array
    {
        $lng = (float) $options['lng'];
        $lat = (float) $options['lat'];
        $cacheKey = $this->cacheKey('reverse', $options);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($options, $lng, $lat): array {
            $url = sprintf(
                'https://api.mapbox.com/geocoding/v5/mapbox.places/%s,%s.json',
                $lng,
                $lat,
            );

            $response = Http::timeout(12)->get($url, $this->queryParams($options));

            $payload = $this->applyAddressPolicy($this->normalizeResponse($response->throw()->json()), $options);
            $feature = $payload['features'][0] ?? null;

            return [
                'feature' => is_array($feature) ? $feature : null,
                'error' => $payload['error'],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    protected function queryParams(array $options, array $extra = []): array
    {
        $token = config('filament-flex-fields.mapbox.access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Mapbox access token is not configured.');
        }

        $params = [
            'access_token' => $token,
            'language' => (string) ($options['language'] ?? config('filament-flex-fields.mapbox.default_language', 'pl')),
        ];

        if ($options['require_street_number'] ?? false) {
            $params['types'] = 'address';
        } elseif (filled($options['types'] ?? null)) {
            $params['types'] = (string) $options['types'];
        }

        if (is_array($options['countries'] ?? null) && $options['countries'] !== []) {
            $params['country'] = strtolower(implode(',', $options['countries']));
        }

        foreach (['bbox', 'proximity'] as $key) {
            if (is_array($options[$key] ?? null)) {
                $params[$key] = implode(',', $options[$key]);
            }
        }

        return array_merge($params, array_map(
            fn (mixed $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
            $extra,
        ));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{features: list<array<string, mixed>>, error: string|null}
     */
    protected function normalizeResponse(?array $payload): array
    {
        return [
            'features' => is_array($payload['features'] ?? null) ? $payload['features'] : [],
            'error' => isset($payload['message']) ? (string) $payload['message'] : null,
        ];
    }

    /**
     * @param  array{features: list<array<string, mixed>>, error: string|null}  $payload
     * @param  array<string, mixed>  $options
     * @return array{features: list<array<string, mixed>>, error: string|null}
     */
    private function applyAddressPolicy(array $payload, array $options): array
    {
        if ($options['require_street_number'] ?? false) {
            $payload['features'] = array_values(array_filter($payload['features'], fn (array $feature): bool => in_array('address', $feature['place_type'] ?? [], true)
                && preg_match('/^\d/u', (string) ($feature['address'] ?? '')) === 1));
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function cacheKey(string $mode, array $options): string
    {
        return 'fff-geocode:v4:'.$mode.':'.hash('sha256', json_encode([$options, config('filament-flex-fields.mapbox.access_token')], JSON_THROW_ON_ERROR));
    }

    protected function cacheTtl(): int
    {
        return max(0, (int) config('filament-flex-fields.mapbox.cache_ttl_seconds', 3600));
    }
}
