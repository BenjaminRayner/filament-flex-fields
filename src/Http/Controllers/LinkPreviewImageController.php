<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Http\Controllers;

use Bjanczak\FilamentFlexFields\Support\LinkPreview\PreviewRequestLimiter;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\RemotePageFetcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LinkPreviewImageController
{
    public function __invoke(Request $request, RemotePageFetcher $fetcher): Response
    {
        if (! $request->hasValidSignature(absolute: false)) {
            abort(403);
        }
        PreviewRequestLimiter::check($request, 'image');
        $url = $request->query('url');
        if (! is_string($url) || strlen($url) > 2048) {
            abort(400);
        }
        $image = $fetcher->fetch($url, image: true);
        if ($image === null) {
            abort(404);
        }
        $details = @getimagesizefromstring($image['body']);
        if (! is_array($details) || ! in_array($details['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true) || $details[0] * $details[1] > 8388608) {
            abort(422);
        }

        return response($image['body'], 200, [
            'Content-Type' => $details['mime'], 'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
