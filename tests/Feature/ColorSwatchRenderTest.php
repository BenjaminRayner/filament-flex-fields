<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ColorSwatchField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTrafficSplitForm;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableTrafficSplitForm::$formSchema = [];
});

it('renders separate owners and correct selection before Alpine starts', function (): void {
    TestableTrafficSplitForm::$formSchema = [
        ColorSwatchField::make('light')->colors(['white' => '#fff', 'black' => '#000'])->default('white'),
        ColorSwatchField::make('dark')->colors(['white' => '#fff', 'black' => '#000'])->default('black'),
        ColorSwatchField::make('locked')->colors(['white' => '#fff'])->default('white')->disabled(),
    ];
    $html = Livewire::test(TestableTrafficSplitForm::class)->html(false);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-fff-asset-consumer="color-swatch"]')->length)->toBe(3)
        ->and($xpath->query('//*[@role="radio"][@aria-checked="true"]')->length)->toBe(3)
        ->and($xpath->query('//*[@role="radio"][@tabindex="0"]')->length)->toBe(2)
        ->and($xpath->query('//*[@role="radio"][@disabled]')->length)->toBe(1)
        ->and($html)->toContain('colorSwatchFormComponent', 'x-fff-load', '--fff-color-swatch-pill: #fff');
});

it('preserves instance identity during state updates and only stores a palette key', function (): void {
    TestableTrafficSplitForm::$formSchema = [ColorSwatchField::make('accent')->colors(['white' => '#fff', 'black' => '#000'])->default('white')->required()];
    $component = Livewire::test(TestableTrafficSplitForm::class);
    $owner = static function (string $html): string {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return (new DOMXPath($document))->query('//*[@role="radiogroup"]')->item(0)->getAttribute('wire:key');
    };
    $initial = $owner($component->html(false));
    $component->set('data.accent', 'black')->call('save')->assertHasNoErrors();
    expect($component->get('saved.accent'))->toBe('black')
        ->and($owner($component->html(false)))->toBe($initial);
    $component->call('$refresh')->assertHasNoErrors();
    expect($owner($component->html(false)))->toBe($initial);
});

it('rejects forged state through actual Livewire form validation', function (mixed $value): void {
    TestableTrafficSplitForm::$formSchema = [ColorSwatchField::make('accent')->colors(['white' => '#fff', 'black' => '#000'])->required()];
    Livewire::test(TestableTrafficSplitForm::class)->set('data.accent', $value)->call('save')->assertHasErrors(['data.accent']);
})->with(['unknown' => ['forged'], 'hex instead of key' => ['#fff'], 'array' => [['white']], 'number' => [1], 'empty required' => [null]]);
