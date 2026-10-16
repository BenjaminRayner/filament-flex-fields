<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RatingField;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableRatingForm;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableRatingTable;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableRatingForm::$formSchema = [];
});

it('renders accessible interactive buttons and fractional display without a script owner', function (): void {
    TestableRatingForm::$formSchema = [
        RatingField::make('score')->default(3), RatingField::make('other'),
        RatingField::make('average')->readOnly()->default(3.7), RatingField::make('disabled')->disabled()->default(4),
    ];
    $html = Livewire::test(TestableRatingForm::class)->html(false);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@role="radio"]')->length)->toBe(15)
        ->and($xpath->query('//*[@role="radio"][@aria-checked="true"]')->length)->toBe(2)
        ->and($xpath->query('//*[@role="radio"][@tabindex="0"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-fff-asset-consumer="rating-visuals"]')->length)->toBe(1)
        ->and($xpath->query('//*[@role="img"]')->item(0)->getAttribute('aria-label'))->toContain('3.7 / 5')
        ->and($html)->toContain('width: 70%')->not->toContain('x-init="init()"');
});

it('rejects forged rating values in the authoritative form', function (mixed $value): void {
    TestableRatingForm::$formSchema = [RatingField::make('score')];
    $component = Livewire::test(TestableRatingForm::class)->set('data.score', $value)->call('save')->assertHasErrors(['data.score']);
    expect($component->get('saved'))->toBeNull();
})->with(['negative' => [-1], 'too high' => [6], 'fraction' => [3.5], 'boolean' => [true], 'array' => [[]],
    'nested' => [[['3']]], 'junk' => ['3abc'], 'infinite' => ['INF'], 'nonfinite' => ['NaN']]);

it('saves canonical integers and optional null', function (mixed $value, mixed $expected): void {
    TestableRatingForm::$formSchema = [RatingField::make('score')];
    $component = Livewire::test(TestableRatingForm::class)->set('data.score', $value)->call('save')->assertHasNoErrors();
    expect($component->get('saved.score'))->toBe($expected);
})->with([[0, 0], [1, 1], ['4', 4], [5, 5], [null, null], ['', null]]);

it('requires a nonempty rating and validates read-only fractional values on save', function (): void {
    TestableRatingForm::$formSchema = [RatingField::make('score')->required(), RatingField::make('average')->readOnly()];
    $component = Livewire::test(TestableRatingForm::class)->call('save')->assertHasErrors(['data.score']);
    $component->set('data.score', 4)->set('data.average', '3.7')->call('save')->assertHasNoErrors();
    expect($component->get('saved.average'))->toBe(3.7);
    $component->set('data.average', 5.1)->call('save')->assertHasErrors(['data.average']);
});

it('registers field and column with shared CSS and no column script dependency', function (): void {
    $registry = app(ComponentRegistry::class);
    $column = $registry->definition('rating-column');
    $field = $registry->definition('rating-field');
    expect($column->kind)->toBe(ComponentKind::Column)
        ->and($column->stylesFrom)->toBe(['rating-visuals'])
        ->and($column->entry)->toBeNull()
        ->and($field->stylesFrom)->toBe(['rating-visuals'])
        ->and(FlexFieldAssets::alpineChunksFor('rating-column'))->toBe([]);
});

it('emits one independent asset boundary for each real Filament table cell', function (): void {
    $html = Livewire::test(TestableRatingTable::class)->html(false);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $markers = $xpath->query('//*[@data-fff-asset-consumer="rating-column"]');
    $owners = [];
    foreach ($markers as $marker) {
        $owners[] = $marker->getAttribute('data-fff-asset-consumer-id');
    }
    expect($markers->length)->toBe(4)
        ->and(count(array_unique($owners)))->toBe(4)
        ->and($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," fff-rating-column ")]')->length)->toBe(4)
        ->and($html)->not->toContain('rating-field.js');
});
