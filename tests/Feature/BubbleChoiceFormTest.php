<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BubbleChoiceField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableBubbleChoiceForm;
use Livewire\Livewire;

beforeEach(function () {
    TestableBubbleChoiceForm::$formSchema = [BubbleChoiceField::make('choices')->options([
        'a' => 'Alpha', 'b' => ['label' => 'Beta', 'description' => 'Second'],
        'disabled' => ['label' => 'Disabled', 'disabled' => true], 0 => 'Zero',
    ])];
});
afterEach(function () {
    TestableBubbleChoiceForm::$formSchema = [];
});

it('renders named SSR options before JS with no duplicate initialization', function () {
    $html = Livewire::test(TestableBubbleChoiceForm::class)->html();
    expect(substr_count($html, 'data-bubble-value='))->toBe(4)
        ->and(substr_count($html, 'data-bubble-scallop'))->toBe(1)
        ->and($html)->toContain('Alpha', 'Beta', 'aria-pressed="false"', 'wire:key=')
        ->not->toContain('x-for=', 'x-init="init()"');
});

it('saves valid canonical string keys preserving order', function () {
    $form = Livewire::test(TestableBubbleChoiceForm::class)->set('data.choices', ['b', 0, 'a'])->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['choices' => ['b', '0', 'a']]);
});

it('rejects invalid submitted state without silently repairing it', function (mixed $state) {
    $form = Livewire::test(TestableBubbleChoiceForm::class)->set('data.choices', $state)->call('save')->assertHasErrors(['data.choices']);
    expect($form->get('saved'))->toBeNull();
})->with([
    'unknown' => [['a', 'unknown']], 'duplicate' => [['a', 'a']], 'disabled' => [['disabled']],
    'mixed duplicate' => [[0, '0']], 'scalar' => ['a'], 'boolean' => [true],
    'nested' => [[['a']]], 'associative' => [['key' => 'a']], 'float' => [[1.2]],
    'control' => [["a\0"]], 'oversized key' => [[str_repeat('a', 513)]],
]);

it('enforces counts including required empty and exact zero', function () {
    foreach ([['required', []], ['min', ['a']], ['max', ['a', 'b']], ['exact', ['a']]] as [$kind, $state]) {
        $field = BubbleChoiceField::make('choices')->options(['a' => 'A', 'b' => 'B']);
        match ($kind) {
            'required' => $field->required(), 'min' => $field->minItems(2), 'max' => $field->maxItems(1), 'exact' => $field->exactItems(2)
        };
        TestableBubbleChoiceForm::$formSchema = [$field];
        Livewire::test(TestableBubbleChoiceForm::class)->set('data.choices', $state)->call('save')->assertHasErrors(['data.choices']);
    }
    TestableBubbleChoiceForm::$formSchema = [BubbleChoiceField::make('choices')->options(['a' => 'A'])->maxItems(0)];
    Livewire::test(TestableBubbleChoiceForm::class)->set('data.choices', ['a'])->call('save')->assertHasErrors(['data.choices']);
    Livewire::test(TestableBubbleChoiceForm::class)->set('data.choices', [])->call('save')->assertHasNoErrors();
});

it('revalidates changed option closures against current server configuration', function () {
    $available = ['a' => 'Alpha'];
    TestableBubbleChoiceForm::$formSchema = [BubbleChoiceField::make('choices')->options(function () use (&$available): array {
        return $available;
    })];
    $form = Livewire::test(TestableBubbleChoiceForm::class)->set('data.choices', ['a']);
    $available = ['b' => 'Beta'];
    $form->call('save')->assertHasErrors(['data.choices']);
});

it('keeps independent instance geometry and readonly input policy', function () {
    TestableBubbleChoiceForm::$formSchema = [BubbleChoiceField::make('first')->options(['a' => 'Alpha']), BubbleChoiceField::make('second')->options(['b' => 'Beta'])->readOnly()];
    $html = Livewire::test(TestableBubbleChoiceForm::class)->html();
    preg_match_all('/<clipPath id="([^"]+)"/', $html, $matches);
    expect($matches[1])->toHaveCount(4)->and(array_unique($matches[1]))->toHaveCount(4)
        ->and($html)->toContain('aria-readonly="true"');
});
