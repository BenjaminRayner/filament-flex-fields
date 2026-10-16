<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Http\Controllers;

use Bjanczak\FilamentFlexFields\Support\LinkPreview\LinkPreviewUrl;
use Bjanczak\FilamentFlexFields\Support\LinkPreview\MetadataPresentation;
use Bjanczak\FilamentFlexFields\Support\LinkPreview\PreviewRequestLimiter;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\UrlMetaScraper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class UrlMetaScrapeController extends Controller
{
    public function scrape(Request $request, UrlMetaScraper $scraper): JsonResponse
    {
        PreviewRequestLimiter::check($request);
        $url = $request->query('url');
        if (! is_string($url) || strlen($url) > 2048 || ! LinkPreviewUrl::isPublicSyntax($url)) {
            return response()->json(['error' => __('filament-flex-fields::default.link_preview.invalid_url')], 400);
        }

        return response()->json(MetadataPresentation::forBrowser($scraper->scrape($url)))
            ->header('Cache-Control', 'private, no-store');
    }
}
