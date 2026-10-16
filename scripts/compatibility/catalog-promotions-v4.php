<?php

declare(strict_types=1);

require __DIR__.'/flex-catalog-v4.php';

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogBanner;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogValue;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionDocument;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionFeed;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionRepository;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

$app['config']->set('cache.default', 'array');
$document = file_get_contents(dirname(__DIR__, 2).'/.github/ads.json');
$decoded = PromotionDocument::decode($document);
locationCheck(count($decoded) === 2, 'Versioned public feed incomplete');
locationCheck((new ReflectionClass(PromotionFeed::class))->getConstructor()->isPrivate(), 'Promotion transport can be configured by consumers');
foreach (['{"schemaVersion":2,"banners":[]}', '{"schemaVersion":1,"banners":{}}', str_repeat('x', PromotionDocument::MAX_BYTES + 1), json_encode(['schemaVersion' => 1, 'banners' => [$decoded[0], $decoded[0]]]), json_encode(['schemaVersion' => 1, 'banners' => array_fill(0, 13, $decoded[0])])] as $invalid) {
    try {
        PromotionDocument::decode($invalid);
        throw new RuntimeException('Invalid remote feed admitted');
    } catch (InvalidArgumentException|JsonException) {
    }
}
locationReject(fn () => new CatalogBanner('invalid-style', 'Title', 'Description', 'https://example.com', theme: 'anything', overlayOpacity: 2));
$repo = new PromotionRepository;
$feed = PromotionFeed::official();
Cache::forget($feed->key);
$round = 0;
Http::preventStrayRequests();
Http::fake(function ($request) use (&$round, $document) {
    $round++;

    return match ($round) {
        1 => Http::response($document, 200, ['ETag' => '"version-one"', 'Last-Modified' => 'Sun, 04 Oct 2026 00:00:00 GMT']),
        2 => Http::response('', 304),
        3, 5 => Http::response('', 503),
        4 => Http::response('{"schemaVersion":999,"banners":[]}', 200),
        6 => Http::response('{"schemaVersion":1,"banners":[]}', 200),
        default => Http::response('', 302, ['Location' => 'http://localhost/private']),
    };
});
locationCheck($repo->snapshot()->banners === null && $repo->snapshot()->refreshDue, 'Cold snapshot is not a nonblocking cache read');
Http::assertNothingSent();
locationCheck(count($repo->refresh()->banners) === 2, 'Remote refresh failed');
$repo->refresh();
Http::assertSentCount(1);
$expireRefresh = static function () use ($feed): void {
    $record = Cache::get($feed->key);
    $record['nextAttemptAt'] = time() - 1;
    Cache::put($feed->key, $record, 90000);
};
$expireRefresh();
locationCheck(count($repo->refresh()->banners) === 2, '304 erased cached document');
Http::assertSent(fn ($request) => $request->hasHeader('If-None-Match', '"version-one"') && $request->hasHeader('If-Modified-Since'));
$expireRefresh();
locationCheck(count($repo->refresh()->banners) === 2, 'Outage erased last valid banners');
$repo->refresh();
Http::assertSentCount(3);
$expireRefresh();
locationCheck(count($repo->refresh()->banners) === 2, 'Invalid remote document replaced valid banners');
$record = Cache::get($feed->key);
$record['validUntil'] = time() - 1;
$record['nextAttemptAt'] = time() - 1;
Cache::put($feed->key, $record, 90000);
locationCheck($repo->refresh()->banners === null, 'Expired stale document extended forever during outage');
$expireRefresh();
locationCheck($repo->refresh()->banners === [], 'Empty remote feed cannot intentionally hide promotions');
$expireRefresh();
$lock = Cache::lock($feed->key.'.lock', 10);
$lock->get();
$repo->refresh();
Http::assertSentCount(6);
$lock->release();
$repo->refresh();
locationCheck($repo->snapshot()->banners === [], 'Redirect response replaced cached metadata');
Http::assertSentCount(7);
$background = new CatalogBanner('image-probe', 'Title', 'Text', 'https://example.com', backgroundImage: 'https://example.com/background.jpg', theme: 'dark', backgroundPosition: 'top', overlayOpacity: .6);
$html = Blade::render('filament-flex-fields::catalog.banner', ['catalog' => ['bannerMode' => 'slider', 'banners' => [$background->presentation()]]]);
locationCheck(str_contains($html, 'fff-flex-banner__background--top') && str_contains($html, 'opacity:0.6') && ! str_contains($html, 'fff-flex-banner__arrow--previous'), 'Background options or single-slide controls invalid');
echo 'PASS: cache-only first paint, validated bounded GitHub feed, conditional 304, single-flight lock, failure backoff, bounded stale retention, empty source and background presentation.'.PHP_EOL;

$missingEnglish = $decoded[0];
$missingEnglish['title'] = ['pl' => 'Tylko polski'];
locationReject(fn () => PromotionDocument::decode(json_encode(['schemaVersion' => 1, 'banners' => [$missingEnglish]], JSON_THROW_ON_ERROR)));
locationCheck(CatalogValue::localized(['en' => 'English', 'pl' => 'Polski'], 'de-DE') === 'English', 'Remote language fallback is not English');
locationCheck(CatalogValue::localized(['en' => 'English', 'pl' => 'Polski'], 'pl-PL') === 'Polski', 'Regional locale fallback failed');
foreach ([['interval' => 2999], ['interval' => 60001], ['autoplay' => 'yes'], ['arrows' => 1], ['unknown' => true]] as $options) {
    locationReject(fn () => PromotionDocument::decode(json_encode(['schemaVersion' => 1, 'banners' => [], 'slider' => $options], JSON_THROW_ON_ERROR)));
}
locationReject(fn () => new CatalogBanner('too-long', str_repeat('ą', CatalogValue::LABEL_LIMIT + 1), 'Description', 'https://example.com'));
locationReject(fn () => new ComponentPresentation('Label', str_repeat('a', CatalogValue::DESCRIPTION_LIMIT + 1), CatalogCategory::Colors));
locationCheck(PromotionDocument::options($document)['autoplay'] && PromotionDocument::options($document)['interval'] === 7000, 'Publisher slider settings ignored');
$publicConfig = require dirname(__DIR__, 2).'/config/filament-flex-fields.php';
locationCheck(! isset($publicConfig['catalog']) && ! isset($publicConfig['playground']) && array_keys($publicConfig['navigation']) === ['sort'], 'Editable public catalog settings remain');
echo 'PASS: mandatory English remote copy, locale fallback, strict publisher autoplay options, shared Unicode content limits, minimal permanent-navigation config and isolated examples.'.PHP_EOL;
