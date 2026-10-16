<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Http\Controllers\MapboxGeocodingProxyController;
use Bjanczak\FilamentFlexFields\Support\Components\Routing\ComponentRoute;

return [
    new ComponentRoute(
        id: 'search',
        uri: 'geocode/search',
        controller: MapboxGeocodingProxyController::class,
        action: 'search',
        methods: ['POST'],
        name: 'filament-flex-fields.geocode.search',
        prefixConfig: 'filament-flex-fields.mapbox.proxy_prefix',
        middlewareConfig: 'filament-flex-fields.mapbox.proxy_middleware',
    ),
    new ComponentRoute(
        id: 'reverse',
        uri: 'geocode/reverse',
        controller: MapboxGeocodingProxyController::class,
        action: 'reverse',
        methods: ['POST'],
        name: 'filament-flex-fields.geocode.reverse',
        prefixConfig: 'filament-flex-fields.mapbox.proxy_prefix',
        middlewareConfig: 'filament-flex-fields.mapbox.proxy_middleware',
    ),
];
