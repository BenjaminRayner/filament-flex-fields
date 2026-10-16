<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\LinkPreview\Validation\LinkPreviewUrlRule;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\UrlMetaScraper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

it('parses open graph metadata from the head section only', function () {
    $html = <<<'HTML'
    <!doctype html>
    <html>
    <head>
        <title>Fallback Title</title>
        <meta property="og:title" content="OG Title">
        <meta property="og:description" content="OG Description">
        <meta property="og:image" content="/images/preview.jpg">
    </head>
    <body><p>Large body content that should never be parsed.</p></body>
    </html>
    HTML;

    $meta = app(UrlMetaScraper::class)->parseFromHtml($html, 'https://example.com/blog/post');

    expect($meta)->toMatchArray([
        'title' => 'OG Title',
        'description' => 'OG Description',
        'image' => 'https://example.com/images/preview.jpg',
    ]);
});

it('falls back to twitter and standard meta tags when open graph is missing', function () {
    $html = <<<'HTML'
    <head>
        <meta name="twitter:title" content="Twitter Title">
        <meta name="twitter:description" content="Twitter Description">
        <meta name="twitter:image" content="https://cdn.example.com/card.png">
    </head>
    HTML;

    $meta = app(UrlMetaScraper::class)->parseFromHtml($html, 'https://example.com');

    expect($meta)->toMatchArray([
        'title' => 'Twitter Title',
        'description' => 'Twitter Description',
        'image' => 'https://cdn.example.com/card.png',
    ]);
});

it('returns title only when no other metadata is present', function () {
    $html = '<head><title>Only Title</title></head>';

    $meta = app(UrlMetaScraper::class)->parseFromHtml($html, 'https://example.com');

    expect($meta)->toBe([
        'title' => 'Only Title',
    ]);
});

it('rejects data uri images from open graph metadata', function () {
    $html = <<<'HTML'
    <head>
        <meta property="og:image" content="data:image/png;base64,iVBORw0KGgo=">
    </head>
    HTML;

    $meta = app(UrlMetaScraper::class)->parseFromHtml($html, 'https://example.com');

    expect($meta)->toBe([]);
});

it('blocks localhost and loopback scrape targets', function (string $url) {
    expect(app(UrlMetaScraper::class)->isScrapableUrl($url))->toBeFalse();
})->with([
    'localhost' => ['http://localhost/page'],
    'localhost subdomain' => ['http://app.localhost/page'],
    '127.0.0.1' => ['http://127.0.0.1/page'],
    'ipv6 loopback' => ['http://[::1]/page'],
]);

it('blocks private, link-local, and metadata hostnames', function (string $url) {
    expect(app(UrlMetaScraper::class)->isScrapableUrl($url))->toBeFalse();
})->with([
    '10.x' => ['http://10.0.0.1/internal'],
    '172.16.x' => ['http://172.16.0.1/internal'],
    '192.168.x' => ['http://192.168.1.1/internal'],
    '169.254.x' => ['http://169.254.169.254/latest/meta-data/'],
    'metadata.google.internal' => ['http://metadata.google.internal/computeMetadata/v1/'],
    'metadata.goog' => ['http://metadata.goog/computeMetadata/v1/'],
    'local domain' => ['http://app.local/admin'],
    'internal domain' => ['http://service.internal/admin'],
]);

it('blocks non-http schemes and malformed urls', function (string $url) {
    expect(app(UrlMetaScraper::class)->isScrapableUrl($url))->toBeFalse();
})->with([
    'ftp' => ['ftp://example.com/file'],
    'file' => ['file:///etc/passwd'],
    'data' => ['data:text/html,hello'],
    'not-a-url' => ['not-a-url'],
]);

it('allows public address targets without relying on external DNS', function () {
    expect(app(UrlMetaScraper::class)->isScrapableUrl('https://93.184.216.34/page'))->toBeTrue();
});

it('ignores malformed metadata image URLs without losing text', function () {
    $meta = app(UrlMetaScraper::class)->parseFromHtml('<head><title>Still valid</title><meta property="og:image" content="https://[broken"></head>', 'https://example.com');
    expect($meta)->toBe(['title' => 'Still valid']);
});

it('fetches a bounded head from larger pages and resolves against the final redirect URL', function () {
    config()->set('filament-flex-fields.link_preview.cache_ttl_seconds', 60);
    Http::fake([
        'https://93.184.216.34/start' => Http::response('', 302, ['Location' => '/folder/page']),
        'https://93.184.216.34/folder/page' => Http::response('<head><title>Unicode — Łódź</title><meta property="og:image" content="image.jpg"></head>'.str_repeat('a', 200000), 200, ['Content-Type' => 'text/html']),
    ]);
    $scraper = app(UrlMetaScraper::class);
    expect($scraper->scrape('https://93.184.216.34/start'))->toMatchArray(['title' => 'Unicode — Łódź', 'image' => 'https://93.184.216.34/folder/image.jpg']);
    expect($scraper->cached('https://93.184.216.34/start'))->toMatchArray(['title' => 'Unicode — Łódź']);
    $scraper->scrape('https://93.184.216.34/start');
    Http::assertSentCount(2);
});

it('validates stored values independently from preview availability', function (mixed $value, bool $valid) {
    $validator = Validator::make(['url' => $value], ['url' => ['nullable', 'string', 'max:2048', new LinkPreviewUrlRule]]);
    expect($validator->passes())->toBe($valid);
})->with([
    ['https://example.com/page', true], ['https://user:pass@example.com', false],
    ['javascript:alert(1)', false], [['url' => 'https://example.com'], false],
    ['https://example.com:8080/page', false], ['https://example.com/'.str_repeat('a', 2048), false],
    [null, true],
]);
