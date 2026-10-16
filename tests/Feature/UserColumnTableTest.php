<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Filament\Support\Facades\FilamentAsset;

/** @return list<string> */
function columnStylesheetUrls(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML($html);
    $urls = [];
    foreach ((new DOMXPath($document))->query('//*[@data-fff-stylesheets]') as $node) {
        $urls = [...$urls, ...json_decode($node->getAttribute('data-fff-stylesheets'), true, flags: JSON_THROW_ON_ERROR)];
    }

    return $urls;
}

it('registers only user display and user column bundles for the column component', function (): void {
    expect(FlexFieldAssets::stylesheetsFor('user-column'))
        ->toBe(['user-display', 'user-column']);
});

it('renders queued table column stylesheets from the styles after hook', function (): void {
    $blade = file_get_contents(__DIR__.'/../../resources/views/partials/queued-stylesheets.blade.php');

    expect($blade)
        ->toContain('FlexFieldStylesheetQueue::pending()')
        ->toContain('FlexFieldStylesheetQueue::pendingConsumers()')
        ->toContain('emit-assets')
        ->toContain("table-columns.'.\$rootConsumer['component']");

    $emitBlade = file_get_contents(__DIR__.'/../../resources/views/partials/emit-assets.blade.php');

    expect($emitBlade)
        ->toContain('data-fff-asset-batch')
        ->toContain('data-fff-stylesheets');
});

it('emits one root CRG consumer owning user-display and user-column together', function (): void {
    FlexFieldStylesheetQueue::reset();
    FlexFieldStylesheetQueue::enqueueFor('user-column');

    expect(FlexFieldStylesheetQueue::pendingConsumers())->toBe([
        [
            'component' => 'user-column',
            'stylesheets' => ['user-display', 'user-column'],
            'chunks' => [],
        ],
    ]);

    $html = view('filament-flex-fields::partials.queued-stylesheets')->render();

    expect($html)
        ->toContain('data-fff-asset-consumer="user-column"')
        ->toContain('data-fff-asset-consumer-id="table-columns.user-column"')
        ->not->toContain('data-fff-asset-consumer="user-display"')
        ->not->toContain('data-fff-asset-consumer-id="table-columns"');

    expect(columnStylesheetUrls($html))->toContain(
        FlexFieldAssets::stylesheetHref('user-display'), FlexFieldAssets::stylesheetHref('user-column'),
    );

    expect(substr_count($html, 'data-fff-asset-batch'))->toBe(1);
});

it('emits distinct root consumers when multiple table columns share a page', function (): void {
    FlexFieldStylesheetQueue::reset();
    FlexFieldStylesheetQueue::enqueueFor('rating-column');
    FlexFieldStylesheetQueue::enqueueFor('icon-column');
    FlexFieldStylesheetQueue::enqueueFor('user-column');

    $html = view('filament-flex-fields::partials.queued-stylesheets')->render();

    expect($html)
        ->toContain('data-fff-asset-consumer-id="table-columns.rating-column"')
        ->toContain('data-fff-asset-consumer-id="table-columns.icon-column"')
        ->toContain('data-fff-asset-consumer-id="table-columns.user-column"')
        ->and(columnStylesheetUrls($html))->toContain(FlexFieldAssets::stylesheetHref('user-display'));

    expect(substr_count($html, 'data-fff-asset-batch'))->toBe(3);
});

it('emits independent cell boundaries with shared assets and no BODY_END duplication', function (): void {
    FlexFieldStylesheetQueue::reset();

    $markup = FlexFieldStylesheetQueue::emitDomRootConsumerMarkup('user-column', 'lw-table-1.user-column');

    expect($markup)
        ->toContain('data-fff-asset-consumer="user-column"')
        ->toContain('data-fff-asset-consumer-id="lw-table-1.user-column"')
        ->and(columnStylesheetUrls($markup))->toContain(
            FlexFieldAssets::stylesheetHref('user-display'), FlexFieldAssets::stylesheetHref('user-column'),
        )
        ->and(FlexFieldStylesheetQueue::emitDomRootConsumerMarkup('user-column', 'lw-table-1.user-column'))
        ->toContain('data-fff-asset-consumer-id="lw-table-1.user-column"')
        ->and(FlexFieldStylesheetQueue::pending())->toBe([])
        ->and(view('filament-flex-fields::partials.queued-stylesheets')->render())->toBe('');
});

it('registers unified asset injector for flex fields lazy assets', function (): void {
    $blade = file_get_contents(__DIR__.'/../../resources/views/partials/flex-field-asset-injector.blade.php');

    expect($blade)
        ->toContain('FlexFieldAssets::ASSET_INJECTOR_SCRIPT_ID')
        ->toContain('FilamentAsset::getScriptSrc')
        ->toContain('data-navigate-track');

    app(RuntimeRenderContext::class)->enableStandalone();
    $html = view('filament-flex-fields::partials.flex-field-asset-injector')->render();
    expect($html)
        ->toContain(FilamentAsset::getScriptSrc(
            FlexFieldAssets::ASSET_INJECTOR_SCRIPT_ID,
            FilamentFlexFieldsPlugin::PACKAGE_NAME,
        ))
        ->toContain('data-navigate-track')
        ->and(view('filament-flex-fields::partials.flex-field-asset-injector')->render())->toBe('');
});
