<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;

it('declares asset components for every field type without invalid ids', function (): void {
    foreach (FieldType::cases() as $type) {
        $components = $type->assetComponents();

        expect($components)->toBeArray();

        foreach ($components as $component) {
            expect($component)->toBeString()->not->toBeEmpty();

            $resolved = FlexFieldAssets::resolveStylesheetComponent($component);
            $stylesheets = FlexFieldAssets::stylesheetsFor($component);

            expect($resolved)->toBeString()->not->toBeEmpty()
                ->and($stylesheets)->not->toBeEmpty(
                    "FieldType [{$type->value}] asset component [{$component}] must resolve to at least one lazy stylesheet.",
                );
        }
    }
});
