<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Http\Controllers;

use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingOs;
use Bjanczak\FilamentFlexFields\Support\Location\Coordinates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MapboxGeocodingProxyController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $this->ensureWithinRateLimit($request);

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:256'],
            'language' => ['nullable', 'string', 'max:16'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'autocomplete' => ['nullable', 'boolean'],
            'bbox' => ['nullable', 'array', 'size:4'],
            'bbox.*' => ['numeric'],
            'proximity' => ['nullable', 'array', 'size:2'],
            'proximity.*' => ['numeric'],
            'types' => ['nullable', 'string', 'max:128'],
            'countries' => ['nullable', 'array', 'max:250'],
            'countries.*' => ['string', 'size:2', 'regex:/^[A-Za-z]{2}$/D'],
            'street_addresses_only' => ['nullable', 'boolean'],
            'require_street_number' => ['nullable', 'boolean'],
        ]);

        try {
            $validated['bbox'] = Coordinates::bounds($validated['bbox'] ?? null);
            if ($validated['bbox'] !== null && $validated['bbox'][0] >= $validated['bbox'][2]) {
                throw new \InvalidArgumentException('Search bounding box cannot wrap.');
            }
            $validated['proximity'] = isset($validated['proximity']) ? Coordinates::pair($validated['proximity']) : null;
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['geocode' => [__('filament-flex-fields::location.validation.invalid_geometry')]]);
        }

        return response()->json(
            GeocodingOs::provider()->search($validated),
        );
    }

    public function reverse(Request $request): JsonResponse
    {
        $this->ensureWithinRateLimit($request);

        $validated = $request->validate([
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'language' => ['nullable', 'string', 'max:16'],
            'types' => ['nullable', 'string', 'max:128'],
            'countries' => ['nullable', 'array', 'max:250'],
            'countries.*' => ['string', 'size:2', 'regex:/^[A-Za-z]{2}$/D'],
            'street_addresses_only' => ['nullable', 'boolean'],
            'require_street_number' => ['nullable', 'boolean'],
        ]);

        return response()->json(
            GeocodingOs::provider()->reverse($validated),
        );
    }

    protected function ensureWithinRateLimit(Request $request): void
    {
        $key = 'fff-geocode:'.($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'anonymous');
        $maxAttempts = (int) config(
            'filament-flex-fields.geocoding.rate_limit_per_minute',
            config('filament-flex-fields.mapbox.rate_limit_per_minute', 60),
        );

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'geocode' => [__('filament-flex-fields::default.geocoding.rate_limited')],
            ]);
        }

        RateLimiter::hit($key, 60);
    }
}
