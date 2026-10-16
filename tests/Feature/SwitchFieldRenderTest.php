<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableSwitchForm;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableSwitchForm::$formSchema = [];
});

it('renders each layout with independent lazy owner and stable initial checked state', function (): void {
    TestableSwitchForm::$formSchema = [
        SwitchField::make('row')->label('Row')->default(true),
        SwitchField::make('bare')->label('Bare')->inline(),
        SwitchField::make('prefix')->label('Prefix')->inlineWithLabel()->default(true),
        SwitchField::make('suffix')->label('Suffix')->inlineWithLabel()->labelPosition('end'),
        SwitchField::make('card')->label('Card')->layout('card')->badge('New')->description('Description')->disabled(),
    ];
    $html = Livewire::test(TestableSwitchForm::class)->html(false);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $buttons = $xpath->query('//*[@data-switch-button]');
    expect($buttons->length)->toBe(5)
        ->and($xpath->query('//*[@data-fff-asset-consumer="switch"]')->length)->toBe(5)
        ->and($xpath->query('//*[@data-switch-button][@aria-checked="true"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-switch-button][@disabled]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-switch-button][@aria-label]')->length)->toBe(5)
        ->and($html)->not->toContain('x-init="init()"')->not->toContain('window.setTimeout');
    $owners = $xpath->query('//*[@x-data][contains(@x-data,"switchFormComponent")]');
    $keys = [];
    foreach ($owners as $owner) {
        $keys[] = $owner->getAttribute('wire:key');
    }
    expect(count(array_unique($keys)))->toBe(5);
});

it('stores only supported boolean representations', function (mixed $value, bool $expected): void {
    TestableSwitchForm::$formSchema = [SwitchField::make('flag')];
    $component = Livewire::test(TestableSwitchForm::class)->set('data.flag', $value)->call('save')->assertHasNoErrors();
    expect($component->get('saved.flag'))->toBe($expected);
})->with([[true, true], [false, false], [1, true], [0, false], ['1', true], ['0', false], [null, false]]);

it('rejects malformed submissions even for optional switches', function (mixed $value): void {
    TestableSwitchForm::$formSchema = [SwitchField::make('flag')];
    $component = Livewire::test(TestableSwitchForm::class)->set('data.flag', $value)->call('save')->assertHasErrors(['data.flag']);
    expect($component->get('saved'))->toBeNull();
})->with(['array' => [[]], 'nested' => [[['true']]], 'object' => [['key' => 1]], 'string' => ['true'],
    'false text' => ['false'], 'junk' => ['bad'], 'two' => [2], 'fraction' => [.5], 'empty' => ['']]);

it('validates accepted and declined values independently from boolean normalization', function (): void {
    TestableSwitchForm::$formSchema = [SwitchField::make('terms')->accepted(), SwitchField::make('denied')->declined()];
    $component = Livewire::test(TestableSwitchForm::class)->call('save')->assertHasErrors(['data.terms']);
    $component->set('data.terms', true)->call('save')->assertHasNoErrors();
    $component->set('data.denied', true)->call('save')->assertHasErrors(['data.denied']);
});

it('excludes disabled switch values from saved state', function (): void {
    TestableSwitchForm::$formSchema = [SwitchField::make('flag')->disabled()->default(true)];
    $component = Livewire::test(TestableSwitchForm::class)->set('data.flag', false)->call('save')->assertHasNoErrors();
    expect($component->get('saved'))->toBe([]);
});
