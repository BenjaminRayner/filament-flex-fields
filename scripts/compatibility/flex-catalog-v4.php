<?php

declare(strict_types=1);

require __DIR__.'/component-schema-v4.php';

use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\CategoryRegistry;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\OfficialPromotions;
use Bjanczak\FilamentFlexFields\Support\Catalog\RecommendedPlugin;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\GravityIcon;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Blade;

$catalog = app(FlexCatalog::class);
locationCheck(count(app(CategoryRegistry::class)->all()) === count(CatalogCategory::cases()), 'Official taxonomy incomplete');
locationCheck(! method_exists(CategoryRegistry::class, 'register'), 'Extensions can invent catalog categories');
$catalogDefinitions = array_filter(app(ComponentRegistry::class)->all(), static fn (ComponentDefinition $definition): bool => $definition->catalog !== null);
locationCheck(count($catalog->entries()) === count($catalogDefinitions), 'Built-in demo metadata incomplete');
$catalogIconOwners = [];
foreach ($catalog->entries() as $entry) {
    locationCheck(count($entry['categories']) === 1 && CatalogCategory::tryFrom($entry['categories'][0]) !== null, 'Nonofficial taxonomy admitted');
    locationCheck($entry['official'] && $entry['installed'] && $entry['author'] === 'FLEX', 'Built-in identity missing');
    if ($entry['icon']) {
        locationCheck(is_file('vendor/janczakb/blade-hugeicons/resources/svg/'.substr($entry['icon'], 10).'.svg'), 'Unknown catalog icon: '.$entry['icon']);
        locationCheck(! isset($catalogIconOwners[$entry['icon']]), 'Catalog icon reused by '.$entry['id'].' and '.($catalogIconOwners[$entry['icon']] ?? ''));
        $catalogIconOwners[$entry['icon']] = $entry['id'];
    }
}
foreach ([
    'select-field' => 'dropdown-field-type',
    'flex-text-input' => 'input-short-text',
    'map-picker' => 'maps-location-02',
    'flex-color-picker' => 'paint-bucket',
    'user-select' => 'co-present',
    'country-map-field' => 'global-search',
    'address-autocomplete' => 'real-estate-01',
    'timezone-field' => 'time-zone',
    'dual-listbox' => 'layout-2-column',
    'date-time-fields' => 'calendar-clock',
    'date-picker' => 'calendar-01',
    'date-range-picker' => 'calendar-sync',
    'time-picker' => 'time-quarter-02',
    'date-periods' => 'calendar-03',
    'schedule-field' => 'chat-delay',
    'color-swatch' => 'swatch-book',
    'track-slider' => 'form',
    'flex-slider' => 'settings-05',
    'price-range' => 'bitcoin-graph',
    'matrix-choice' => 'table-properties',
    'cover-card' => 'text-to-image',
    'calculator-field' => 'calculate',
    'slug-field' => 'link',
    'social-links-field' => 'at',
    'video-field' => 'video-ai',
    'audio-field' => 'audio-waveform',
    'voice-note-recorder-field' => 'ai-concert',
    'link-preview-field' => 'app-window',
    'signature-field' => 'signature',
    'map-zones' => 'maps-circle-02',
    'map-markers' => 'map-pin-plus',
] as $id => $icon) {
    locationCheck($catalog->find($id)['icon'] === 'hugeicons-'.$icon, 'Requested catalog icon missing: '.$id);
}
$english = $catalog->entries();
$app->setLocale('pl');
$polish = $catalog->entries();
foreach ($english as $id => $entry) {
    locationCheck($polish[$id]['label'] !== $entry['label'] && $polish[$id]['description'] !== $entry['description'], 'Untranslated catalog metadata: '.$id);
}
locationCheck($polish['select-field']['label'] === 'Select FLEX', 'Locale cache retained previous language');
$app->setLocale('en');
locationCheck($catalog->entries() === $english, 'Locale snapshot restoration failed');
$app['config']->set('app.env', 'production');
locationCheck(FlexFieldsConfig::isPlaygroundEnabled(), 'FLEX is hidden in production');
$app['config']->set('filament-flex-fields.catalog.enabled', false);
locationCheck(FlexFieldsConfig::isPlaygroundEnabled(), 'Legacy catalog flag can hide permanent FLEX navigation');
$app['config']->set('filament-flex-fields.catalog.enabled', true);
$custom = new ComponentDefinition('custom-discovery-probe', ComponentKind::Field, uses: ['flex-text-input'], catalog: new ComponentPresentation(
    label: ['en' => 'Custom probe', 'pl' => 'Własny komponent'], description: 'Reusable field from another author.', category: CatalogCategory::TextInput, icon: 'hugeicons-grid-view', author: 'Example Author',
));
app(ComponentRegistrar::class)->register($custom);
locationCheck($catalog->find($custom->id)['installed'] && ! $catalog->find($custom->id)['official'], 'Registered custom component did not appear automatically');
locationReject(fn () => app(ComponentRegistrar::class)->register(new ComponentDefinition('fake-official', ComponentKind::Field, catalog: new ComponentPresentation('Fake', 'Fake official identity', CatalogCategory::TextInput, official: true))));
$catalog->registerPlugin(new RecommendedPlugin('external-probe', 'External probe', 'External package.', 'Example Author', 'https://github.com/example/package', CatalogCategory::Controls, composerPackage: 'laravel/framework'));
locationCheck($catalog->find('external-probe')['installed'], 'Composer-installed package missed');
$catalog->registerPlugin(new RecommendedPlugin('absent-probe', 'Absent', 'Absent external package.', 'Example Author', 'https://github.com/example/absent', CatalogCategory::Controls, composerPackage: 'example/absent-probe-package', version: '2.1.0', website: 'https://example.com', privacyPolicy: 'https://example.com/privacy', termsOfService: 'https://example.com/terms'));
locationCheck(! $catalog->find('absent-probe')['installed'], 'Absent Composer package reported installed');
locationCheck($catalog->find('external-probe')['version'] === InstalledVersions::getPrettyVersion('laravel/framework'), 'Installed plugin version does not reflect local Composer metadata');
locationCheck($catalog->find('absent-probe')['version'] === '2.1.0', 'Declared external version omitted');
locationReject(fn () => new ComponentPresentation('Unsafe', 'Unsafe links', CatalogCategory::Controls, website: 'javascript:alert(1)'));
foreach (['select-field', 'absent-probe'] as $id) {
    $information = Blade::render('filament-flex-fields::catalog.information', ['entry' => $catalog->find($id)]);
    locationCheck(! str_contains($information, 'Capabilities'), 'Capabilities leaked into information');
    locationCheck(str_contains($information, '<dt>Version</dt>') === ($id === 'absent-probe'), 'Version visible on built-in component or omitted for external plugin');
    if ($id === 'absent-probe') {
        locationCheck(str_contains($information, 'https://example.com/privacy') && str_contains($information, 'https://example.com/terms'), 'Author links omitted');
    }
}

locationReject(fn () => new RecommendedPlugin('invalid-url', 'Invalid', 'Invalid destination', 'Example', 'javascript:alert(1)', CatalogCategory::Controls));
locationReject(fn () => new ComponentPresentation('No artwork', 'Must have artwork', CatalogCategory::Controls, icon: null));
locationCheck(! method_exists($catalog, 'registerBanner'), 'Host can override official promotions');
$promotionData = ['banners' => OfficialPromotions::fallback(), 'bannerMode' => 'static'];
$staticPromotion = Blade::render('filament-flex-fields::catalog.banner', ['catalog' => $promotionData]);
$sliderPromotion = Blade::render('filament-flex-fields::catalog.banner', ['catalog' => [...$promotionData, 'bannerMode' => 'slider']]);
$emptyPromotion = Blade::render('filament-flex-fields::catalog.banner', ['catalog' => ['banners' => [], 'bannerMode' => 'slider']]);
locationCheck(substr_count($staticPromotion, '<article class="fff-flex-banner__slide ') === 1 && ! str_contains($staticPromotion, 'fff-flex-banner__arrow--previous'), 'Static promotion renders carousel controls or more than one slide');
locationCheck(substr_count($sliderPromotion, '<article class="fff-flex-banner__slide ') === 2 && str_contains($sliderPromotion, 'move(-1)') && str_contains($sliderPromotion, 'move(1)') && str_contains($sliderPromotion, 'fff-flex-banner__dot'), 'Slider controls or slides omitted');
locationCheck(trim($emptyPromotion) === '', 'Empty promotion configuration leaves a visual container');
foreach (['schedule-field', 'flex-color-picker', 'select-field'] as $id) {
    locationCheck(! in_array('flex-catalog', ComponentAssetPlan::for($id)->stylesheets, true), 'Catalog styles leaked into production fields');
    locationCheck(! in_array('flex-catalog.js', ComponentAssetPlan::for($id)->chunks, true), 'Catalog JS leaked into production fields');
}
$card = Blade::render('filament-flex-fields::catalog.card', ['entry' => [...$catalog->find('select-field'), 'url' => '/demo']]);
locationCheck(str_contains($card, 'fff-flex-card__status') && ! str_contains($card, 'fff-flex-card__action'), 'Old list arrows survived presentation');
locationCheck(str_contains($card, 'stroke="white"'), 'Filled verification seal missing');
locationCheck(GravityIcon::Plus === FlexIcon::Plus, 'Legacy PHP alias lost compatibility');
locationCheck($catalog->entries() === $catalog->entries(), 'Catalog snapshots unstable');
$definitions = FlexFieldsPlaygroundRegistry::definitions();
locationCheck(count($definitions) === 70, 'Legacy demo page adapter lost existing demos');
echo 'PASS: closed single-category taxonomy, 70 official demos, custom discovery, production visibility, local Composer detection, reserved verification identity, banners and isolated catalog assets.'.PHP_EOL;
