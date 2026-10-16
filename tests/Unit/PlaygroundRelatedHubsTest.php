<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Support\Playground\PlaygroundRelatedHubs;

it('links layout playground hubs to each other', function (): void {
    $entries = PlaygroundRelatedHubs::viewData('translatable-fields');

    expect($entries['currentSlug'])->toBe('translatable-fields')
        ->and($entries['hubs'])->toHaveCount(2)
        ->and(array_column($entries['hubs'], 'slug'))->toContain('segment-tabs', 'item-card-group')
        ->and(array_column($entries['hubs'], 'slug'))->not->toContain('translatable-fields')
        ->and($entries['hubs'][0]['url'] ?? null)->toBeString()->not->toBeEmpty();
});

it('returns no related hubs for non-layout slugs', function (): void {
    expect(PlaygroundRelatedHubs::slugsFor('phone-field'))->toBe([]);
});

it('maps registered guide pages to customer docs pages', function (): void {
    $docsRoot = dirname(__DIR__, 2).'/docs';

    foreach ([
        'field-intelligence' => 'field-intelligence.md',
        'status-chip-column' => 'status-chip-column.md',
    ] as $slug => $filename) {
        $definition = FlexFieldsPlaygroundRegistry::find($slug);

        expect($definition)->not->toBeNull()
            ->and($definition['docs_path'] ?? null)->toBe($filename)
            ->and($docsRoot.'/'.$filename)->toBeFile();
    }

    expect(FlexFieldsPlaygroundRegistry::find('field-intelligence')['label'])->toBe('Calculated formulas');
});

it('lists documentation exemptions for composition and map variants', function (): void {
    expect(FlexFieldsPlaygroundRegistry::docsExemptSlugs())->toBe(['map-coordinates', 'map-display', 'map-editor', 'map-markers', 'map-zones']);
});

it('does not register removed playground pages', function (string $slug): void {
    expect(FlexFieldsPlaygroundRegistry::find($slug))->toBeNull();
})->with(['form-layouts', 'focus-outline', 'composition-recipes', 'schema-conditions', 'admin-columns', 'user-column', 'rating-column', 'icon-column', 'progress-column', 'signature-preview-column', 'map-pin-column']);
