<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Tests\Support\Docs\FieldTypeDocsMap;

it('includes intelligence documentation for the first playground hub', function (): void {
    expect(FlexFieldsPlaygroundRegistry::firstSlug())->toBe('field-intelligence')
        ->and(is_file(dirname(__DIR__, 2).'/docs/field-intelligence.md'))->toBeTrue();
});

it('maps every field type to a documentation file', function (): void {
    expect(FieldTypeDocsMap::coversAllTypes())->toBeTrue()
        ->and(count(FieldTypeDocsMap::all()))->toBe(count(FieldType::cases()));

    $docsRoot = dirname(__DIR__, 2).'/docs';

    foreach (FieldType::cases() as $type) {
        $docsPath = FieldTypeDocsMap::docsPathFor($type);

        expect($docsPath)
            ->toBeString("FieldType [{$type->value}] must have a FieldTypeDocsMap entry.");

        expect($docsRoot.'/'.$docsPath)
            ->toBeFile("FieldType [{$type->value}] references missing docs file [{$docsPath}].");
    }
});

it('maps documented playground hubs to existing docs files', function (): void {
    $docsRoot = dirname(__DIR__, 2).'/docs';
    $exempt = FlexFieldsPlaygroundRegistry::docsExemptSlugs();

    foreach (FlexFieldsPlaygroundRegistry::definitions() as $slug => $definition) {
        if (in_array($slug, $exempt, true)) {
            continue;
        }

        $docsPath = $definition['docs_path'] ?? FlexFieldsPlaygroundRegistry::docsPathFor($slug);

        expect($docsPath)
            ->not->toBeNull("Playground slug [{$slug}] must declare a docs_path or registry docs map entry.");

        expect($docsRoot.'/'.$docsPath)
            ->toBeFile("Playground slug [{$slug}] references missing docs file [{$docsPath}].");
    }
});
