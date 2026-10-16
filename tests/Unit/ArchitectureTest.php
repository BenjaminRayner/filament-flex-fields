<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\Model;

test('strict types')
    ->expect('Bjanczak\FilamentFlexFields')
    ->toUseStrictTypes();

test('globals')
    ->expect('Bjanczak\\FilamentFlexFields')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

test('concerns are traits')
    ->expect('Bjanczak\FilamentFlexFields\Concerns')
    ->toBeTraits();

test('playground enums are backed enums')
    ->expect([
        'Bjanczak\FilamentFlexFields\Enums\PlaygroundCategory',
        'Bjanczak\FilamentFlexFields\Enums\FieldCategory',
    ])
    ->toBeEnums();

test('playground registry definitions include categories')
    ->expect('Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry')
    ->toHaveMethod('categoryForSlug');

test('optional Media Center displays accept the Filament relationship record argument', function (): void {
    $classes = [
        'Bjanczak\\FilamentMediaCenterPro\\Filament\\Tables\\Columns\\CenterAssetColumn',
        'Bjanczak\\FilamentMediaCenterPro\\Filament\\Infolists\\Components\\CenterAssetInfolist',
    ];
    if (! class_exists($classes[0])) {
        $this->markTestSkipped('The optional Media Center package is not installed.');
    }
    $record = new class extends Model {};
    $record->setAttribute('asset_relation', 'gallery');
    foreach ($classes as $class) {
        $display = $class::make('asset');
        expect($display->getRelationshipName($record))->toBeNull();
        $display->relationship('cover');
        expect($display->getRelationshipName($record))->toBe('cover')
            ->and($display->getRelationshipName())->toBe('cover');
        $display->relationship(fn (Model $record): string => $record->getAttribute('asset_relation'));
        expect($display->getRelationshipName($record))->toBe('gallery');
    }
});
