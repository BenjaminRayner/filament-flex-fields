<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Http\HeadCaptureComplete;
use Bjanczak\FilamentFlexFields\Support\Http\HeadCaptureStream;
use Bjanczak\FilamentFlexFields\Support\LinkPreview\MetadataPresentation;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\MetadataParser;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\Network\PreviewDnsLookup;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\Network\PreviewUrlValidator;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\UrlMetaScraper;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

it('continues through empty and unsafe metadata to the first usable image', function () {
    $meta = app(MetadataParser::class)->parse('<head><title>Fallback title</title><meta property="og:title" content=" "><meta property="og:image" content="http://127.0.0.1/private"><meta property="og:image" content="/real.jpg"><meta property="og:description" content="The social description"><meta name="description" content="Other description"></head>', 'https://example.com/page');
    expect($meta)->toBe(['title' => 'Fallback title', 'description' => 'The social description', 'image' => 'https://example.com/real.jpg']);
});

it('prefers the secure alternate for the first image instead of a later root image', function () {
    $meta = app(MetadataParser::class)->parse('<head><meta property="og:image" content="http://example.com/first.jpg"><meta property="og:image:secure_url" content="https://example.com/secure.jpg"><meta property="og:image" content="https://example.com/later.jpg"></head>', 'https://example.com');
    expect($meta['image'])->toBe('https://example.com/secure.jpg');
});

it('uses valid base URLs and tokenized icon relations without private image admission', function () {
    $meta = app(MetadataParser::class)->parse('<head><base href="https://cdn.example.com/assets/"><link rel="shortcut icon" href="logo.png"></head>', 'https://example.com');
    expect($meta['image'])->toBe('https://cdn.example.com/assets/logo.png');
    expect(app(MetadataParser::class)->parse('<head><base href="http://127.0.0.1/private/"><meta property="og:image" content="cover.jpg"></head>', 'https://example.com/'))->toBe(['image' => 'https://example.com/cover.jpg']);
});

it('extracts JSON-LD graph metadata after malformed scripts without executing scripts', function () {
    $html = '<head><script type="application/ld+json">broken</script><script type="application/ld+json">{"@graph":[{"@type":"Organization","name":"Wrong brand"},{"@type":"WebPage","name":"Actual page","description":"Page description","image":{"@type":"ImageObject","contentUrl":"/structured.jpg"}}]}</script></head>';
    expect(app(MetadataParser::class)->parse($html, 'https://example.com'))->toBe(['title' => 'Actual page', 'description' => 'Page description', 'image' => 'https://example.com/structured.jpg']);
});

it('keeps head-like strings inside scripts comments and quoted attributes from cutting extraction', function () {
    $html = '<head><!-- </head> --><script>const html="</head>";</script><meta name="unused" content="</head>"><title>Valid title</title></head><body><meta property="og:title" content="Injected title"></body>';
    expect(app(MetadataParser::class)->parse($html, 'https://example.com'))->toBe(['title' => 'Valid title']);
});

it('decodes a declared legacy charset and cleans entities and whitespace', function () {
    $html = mb_convert_encoding('<head><meta charset="windows-1252"><title>Café &amp; Tea</title></head>', 'Windows-1252', 'UTF-8');
    expect(app(MetadataParser::class)->parse($html, 'https://example.com'))->toBe(['title' => 'Café & Tea']);
});

it('ends the transfer at the actual head boundary even across arbitrary chunk boundaries', function () {
    $stream = new HeadCaptureStream(Utils::streamFor(fopen('php://temp', 'w+b')), 262144);
    $stream->write('<head><script>let text="</head>";</scr');
    $stream->write('ipt><title>Valid</title></he');
    expect(fn () => $stream->write('ad><body>'.str_repeat('body', 1000)))->toThrow(HeadCaptureComplete::class);
    expect($stream->complete())->toBeTrue()->and($stream->captured())->toBe('<head><script>let text="</head>";</script><title>Valid</title></head>');
    $stream->close();
});

it('bounds unterminated head capture without retaining a huge body', function () {
    $stream = new HeadCaptureStream(Utils::streamFor(fopen('php://temp', 'w+b')), 64);
    expect(fn () => $stream->write('<head>'.str_repeat('a', 100000)))->toThrow(HeadCaptureComplete::class);
    expect(strlen($stream->captured()))->toBe(64);
    $stream->close();
});

it('uses isolated DNS with argument separation and rejects any mixed private destination', function () {
    config()->set('filament-flex-fields.link_preview.dns_strategy', 'isolated');
    Process::fake(['*' => Process::result('["93.184.216.34","::1"]')]);
    expect(app(PreviewUrlValidator::class)->approvedAddresses('https://example.com'))->toBe([]);
    Process::assertRan(fn ($process) => $process->timeout === 2 && is_array($process->command) && end($process->command) === 'example.com');
    Http::assertNothingSent();
});

it('fails closed for malformed or unsuccessful isolated DNS output', function (string $output, int $exit) {
    config()->set('filament-flex-fields.link_preview.dns_strategy', 'isolated');
    Process::fake(['*' => Process::result($output, exitCode: $exit)]);
    expect(app(PreviewDnsLookup::class)->addresses('example.com'))->toBe([]);
})->with([['not json', 0], ['["93.184.216.34"]', 1], ['{"address":"93.184.216.34"}', 0], ['["invalid IP"]', 0]]);

it('answers canonical cache hits without DNS or another remote fetch', function () {
    config()->set('filament-flex-fields.link_preview.cache_ttl_seconds', 60);
    Http::fake(['https://93.184.216.34/page#first' => Http::response('<head><title>Cached title</title></head>', 200, ['Content-Type' => 'text/html'])]);
    $scraper = app(UrlMetaScraper::class);
    expect($scraper->scrape('https://93.184.216.34/page#first'))->toBe(['title' => 'Cached title']);
    $this->mock(PreviewDnsLookup::class, fn ($mock) => $mock->shouldNotReceive('addresses'));
    expect(app(UrlMetaScraper::class)->scrape('https://93.184.216.34/page#second'))->toBe(['title' => 'Cached title']);
    Http::assertSentCount(1);
});

it('signals transient metadata contention instead of returning a permanently empty preview', function () {
    $url = 'https://93.184.216.34/contended';
    $key = 'fff_meta_schema2_'.hash('sha256', $url).'.lock';
    $lock = Cache::lock($key, 20);
    expect($lock->get())->toBeTrue();
    try {
        expect(fn () => app(UrlMetaScraper::class)->scrape($url))->toThrow(ServiceUnavailableHttpException::class);
    } finally {
        $lock->release();
    }
});

it('does not forward ambient application credentials or cookies to the remote page', function () {
    Http::globalOptions(['headers' => ['Authorization' => 'Bearer application-secret', 'Cookie' => 'session=application-secret'], 'auth' => ['username', 'password']]);
    Http::fake(['https://93.184.216.34/clean' => Http::response('<head><title>Clean request</title></head>', 200, ['Content-Type' => 'text/html'])]);
    expect(app(UrlMetaScraper::class)->scrape('https://93.184.216.34/clean'))->toBe(['title' => 'Clean request']);
    Http::assertSent(fn ($request) => ! str_contains(implode(' ', $request->header('Authorization')), 'application-secret') && ! str_contains(implode(' ', $request->header('Cookie')), 'application-secret'));
});

it('shares signed image URLs across renders without exceeding their short expiry', function () {
    try {
        Carbon::setTestNow('2026-10-14 12:01:00');
        $one = MetadataPresentation::forBrowser(['image' => 'https://example.com/photo.png']);
        Carbon::setTestNow('2026-10-14 12:03:00');
        $two = MetadataPresentation::forBrowser(['image' => 'https://example.com/photo.png']);
        expect($two['image'])->toBe($one['image']);
        parse_str(parse_url($two['image'], PHP_URL_QUERY), $query);
        expect((int) $query['expires'] - now()->getTimestamp())->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(600);
        Carbon::setTestNow('2026-10-14 12:05:00');
        expect(MetadataPresentation::forBrowser(['image' => 'https://example.com/photo.png'])['image'])->not->toBe($one['image']);
    } finally {
        Carbon::setTestNow();
    }
});
