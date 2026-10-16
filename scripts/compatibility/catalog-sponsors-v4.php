<?php

declare(strict_types=1);

require __DIR__.'/catalog-promotions-v4.php';

use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionDocument;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionFeed;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionRepository;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\SponsorPreview;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\SponsorProfile;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

$base = ['name' => 'Demo Sponsor', 'profile' => 'https://github.com/example-gold', 'avatar' => 'https://avatars.githubusercontent.com/u/1', 'rank' => 'gold'];
$document = ['schemaVersion' => 1, 'banners' => [], 'sponsors' => [
    [...$base, 'name' => 'Demo Bronze', 'profile' => 'https://github.com/example-bronze', 'rank' => 'bronze'],
    [...$base, 'name' => 'Demo Silver', 'profile' => 'https://github.com/example-silver', 'rank' => 'silver'], $base,
]];
$maximum = array_map(fn ($index) => [...$base, 'name' => 'Sponsor '.$index, 'profile' => 'https://github.com/sponsor-'.$index], range(1, PromotionDocument::MAX_SPONSORS));
locationCheck(count(PromotionDocument::parse(json_encode(['schemaVersion' => 1, 'banners' => [], 'sponsors' => $maximum]))['sponsors']) === PromotionDocument::MAX_SPONSORS, 'Maximum valid sponsor list rejected');
locationReject(fn () => PromotionDocument::parse(json_encode(['schemaVersion' => 1, 'banners' => [], 'sponsors' => [...$maximum, [...$base, 'profile' => 'https://github.com/one-too-many']]])));
locationCheck(count(SponsorPreview::profiles()) === PromotionDocument::MAX_SPONSORS, 'Visual preview does not use the publisher maximum');
$parsed = PromotionDocument::parse(json_encode($document));
locationCheck(array_column($parsed['sponsors'], 'rank') === ['gold', 'silver', 'bronze'], 'Sponsor ranking invalid');
foreach ([null, new stdClass, [$base, $base], array_fill(0, 25, $base), [[...$base, 'rank' => 'platinum']], [[...$base, 'avatar' => 'http://example.com/avatar.png']], [[...$base, 'profile' => 'javascript:alert(1)']], [[...$base, 'name' => str_repeat('ą', 81)]], [[...$base, 'extra' => 'invalid']], [[...$base, 'rank' => true]]] as $invalid) {
    locationReject(fn () => PromotionDocument::parse(json_encode(['schemaVersion' => 1, 'banners' => [], 'sponsors' => $invalid])));
}
locationCheck(PromotionDocument::parse('{"schemaVersion":1,"banners":[]}')['sponsors'] === [], 'Legacy publisher data requires sponsors');
$repo = new PromotionRepository;
$feed = PromotionFeed::official();
Cache::forget($feed->key);
$requests = 0;
Http::swap(new Factory);
Http::preventStrayRequests();
Http::fake(function () use (&$requests, $document) {
    $requests++;

    return match ($requests) {
        1 => Http::response(json_encode($document), 200, ['ETag' => '"sponsors-v1"']),
        2 => Http::response('', 304),
        3 => Http::response('{"schemaVersion":1,"banners":[],"sponsors":[{"bad":"data"}]}', 200),
        default => Http::response('{"schemaVersion":1,"banners":[],"sponsors":[]}', 200),
    };
});
locationCheck($repo->snapshot()->sponsors === [] && $requests === 0, 'Sponsor snapshot performed network IO');
locationCheck(count($repo->refresh()->sponsors) === 3, 'Sponsor metadata failed to refresh');
$expire = function () use ($feed): void {
    $record = Cache::get($feed->key);
    $record['nextAttemptAt'] = time() - 1;
    Cache::put($feed->key, $record, 90000);
};
$expire();
locationCheck(count($repo->refresh()->sponsors) === 3, '304 discarded sponsor profiles');
$expire();
locationCheck(count($repo->refresh()->sponsors) === 3, 'Invalid metadata partially replaced sponsors');
$repo->refresh();
locationCheck($requests === 3, 'Invalid feed retry was not throttled');
$app->setLocale('en');
$sponsors = array_map(fn ($data) => SponsorProfile::fromArray($data)->presentation(), $parsed['sponsors']);
$html = Blade::render('filament-flex-fields::catalog.sponsors', ['catalog' => ['sponsors' => $sponsors]]);
locationCheck(substr_count($html, 'class="fff-flex-sponsor ') === 4 && str_contains($html, PromotionFeed::SPONSORSHIP), 'Sponsor circles or support link missing');
locationCheck(str_contains($html, 'loading="lazy"') && str_contains($html, 'Gold') && ! str_contains($html, 'wire:init'), 'Sponsor avatars request independently of shared feed');
$empty = Blade::render('filament-flex-fields::catalog.sponsors', ['catalog' => ['sponsors' => []]]);
locationCheck(trim($empty) === '', 'Empty sponsor list rendered an upsell or container');
$expire();
locationCheck($repo->refresh()->sponsors === [], 'Empty document did not clear sponsor strip');
$app->setLocale('pl');
locationCheck(SponsorProfile::fromArray($base)->presentation()['rankLabel'] === 'Złoty', 'Sponsor rank lacks Polish localization');
// Disposable visual fixture: never seeds the host app or advertises fictitious supporters.
$css = file_get_contents(dirname(__DIR__, 2).'/resources/dist/css/flex-catalog.css');
file_put_contents('/tmp/flex-sponsors-preview.html', '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>FLEX sponsor layout — demo data</title><style>'.$css.'</style><body><main class="fff-flex-catalog" style="padding:3rem 1.25rem"><h1 style="margin-bottom:2rem;font-size:1.5rem">FLEX · sponsor layout demo</h1>'.$html.'<p style="font-size:.75rem;margin-top:2rem;color:#858590">Visual fixture with demonstration profiles. Production feed remains empty.</p></main></body></html>');
echo 'PASS: closed ranks, bounded HTTPS profiles, stable rank ordering, atomic shared feed cache/304/backoff, locale labels, lazy avatars and zero markup for empty sponsor lists.'.PHP_EOL;
