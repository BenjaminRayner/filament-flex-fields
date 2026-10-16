<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\UrlMeta\UrlMetaScraper;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config()->set('filament-flex-fields.link_preview.rate_limit_per_minute', 2);
    config()->set('filament-flex-fields.link_preview.cache_ttl_seconds', 0);

    $this->withoutMiddleware(Authenticate::class);

    RateLimiter::clear('fff-link-preview:metadata:'.hash('sha256', '127.0.0.1'));
    RateLimiter::clear('fff-link-preview:image:'.hash('sha256', '127.0.0.1'));
});

it('returns scraped metadata for a valid public url', function (): void {
    $this->mock(UrlMetaScraper::class, function ($mock): void {
        $mock->shouldReceive('scrape')
            ->once()
            ->with('https://example.com/page')
            ->andReturn([
                'title' => 'Example Page',
                'description' => 'An example description',
                'image' => 'https://example.com/image.jpg',
            ]);
    });

    $response = $this->getJson(route('filament-flex-fields.url-meta.scrape', ['url' => 'https://example.com/page']))
        ->assertOk()
        ->assertJson([
            'title' => 'Example Page',
            'description' => 'An example description',
        ]);
    parse_str(parse_url($response->json('image'), PHP_URL_QUERY), $parameters);
    expect($parameters['url'])->toBe('https://example.com/image.jpg')
        ->and($parameters)->toHaveKeys(['signature', 'expires']);
});

it('rejects invalid scrape urls', function (): void {
    $this->getJson(route('filament-flex-fields.url-meta.scrape', ['url' => 'not-a-url']))
        ->assertStatus(400)
        ->assertJsonPath('error', __('filament-flex-fields::default.link_preview.invalid_url'));
});

it('rate limits url meta scrape requests', function (): void {
    $this->mock(UrlMetaScraper::class, function ($mock): void {
        $mock->shouldReceive('scrape')->andReturn([]);
    });

    $this->getJson(route('filament-flex-fields.url-meta.scrape', ['url' => 'https://example.com/one']))->assertOk();
    $this->getJson(route('filament-flex-fields.url-meta.scrape', ['url' => 'https://example.com/two']))->assertOk();

    $this->getJson(route('filament-flex-fields.url-meta.scrape', ['url' => 'https://example.com/three']))
        ->assertStatus(429)->assertHeader('Retry-After');
});

it('requires authentication for metadata and images', function () {
    $this->withMiddleware(Authenticate::class);
    $this->getJson(route('filament-flex-fields.url-meta.scrape', ['url' => 'https://93.184.216.34/page']))->assertUnauthorized();
    $image = URL::temporarySignedRoute('filament-flex-fields.url-meta.image', now()->addMinute(), ['url' => 'https://93.184.216.34/image.png'], absolute: false);
    $this->getJson($image)->assertUnauthorized();
});

it('rejects unsigned and tampered image requests without fetching', function () {
    Http::preventStrayRequests();
    $this->getJson(route('filament-flex-fields.url-meta.image', ['url' => 'https://93.184.216.34/image.png']))->assertForbidden();
    $image = URL::temporarySignedRoute('filament-flex-fields.url-meta.image', now()->addMinute(), ['url' => 'https://93.184.216.34/image.png'], absolute: false);
    $this->getJson(str_replace('image.png', 'different.png', $image))->assertForbidden();
    Http::assertNothingSent();
});

it('serves only validated raster images from a signed request', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    Http::fake(['https://93.184.216.34/image.png' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    $image = URL::temporarySignedRoute('filament-flex-fields.url-meta.image', now()->addMinute(), ['url' => 'https://93.184.216.34/image.png'], absolute: false);
    $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('rejects active image markup and private redirect destinations', function (string $body, array $headers, int $status) {
    Http::fake(['https://93.184.216.34/image' => Http::response($body, $status, $headers)]);
    $image = URL::temporarySignedRoute('filament-flex-fields.url-meta.image', now()->addMinute(), ['url' => 'https://93.184.216.34/image'], absolute: false);
    $this->get($image)->assertStatus($status === 302 ? 404 : 422);
    Http::assertSentCount(1);
})->with([
    ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', ['Content-Type' => 'image/svg+xml'], 200],
    ['<script>alert(1)</script>', ['Content-Type' => 'image/png'], 200],
    ['', ['Location' => 'http://127.0.0.1/private'], 302],
]);
