<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Http\Controllers\LinkPreviewImageController;
use Bjanczak\FilamentFlexFields\Http\Controllers\UrlMetaScrapeController;
use Bjanczak\FilamentFlexFields\Support\Components\Routing\ComponentRoute;

return [
    new ComponentRoute(
        id: 'scrape', uri: 'url-meta/scrape', controller: UrlMetaScrapeController::class,
        action: 'scrape', methods: ['GET'], name: 'filament-flex-fields.url-meta.scrape',
        prefixConfig: 'filament-flex-fields.link_preview.route_prefix', middlewareConfig: 'filament-flex-fields.link_preview.middleware',
    ),
    new ComponentRoute(
        id: 'image', uri: 'url-meta/image', controller: LinkPreviewImageController::class,
        methods: ['GET'], name: 'filament-flex-fields.url-meta.image', requiredMiddleware: ['signed:relative'],
        prefixConfig: 'filament-flex-fields.link_preview.route_prefix', middlewareConfig: 'filament-flex-fields.link_preview.middleware',
    ),
];
