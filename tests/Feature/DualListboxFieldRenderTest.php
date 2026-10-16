<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\DualListboxField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableDualListboxForm;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableDualListboxForm::$formSchema = [DualListboxField::make('values')->options(['a' => 'Alpha', 'b' => 'Beta'])->maxItems(1)];
});

it('renders two stable listbox panes and shared search controls', function (): void {
    $html = Livewire::test(TestableDualListboxForm::class)->html(false);
    expect($html)->toContain('data-dual-config')->toContain('aria-multiselectable="true"')->toContain('data-dual-template')
        ->not->toContain('x-init="init()"')->not->toContain('x-for=');
});

it('rejects forged selection at getState', function (mixed $state): void {
    $component = Livewire::test(TestableDualListboxForm::class)->set('data.values', $state);
    expect(fn () => $component->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
})->with([
    'scalar' => ['a'], 'null' => [null], 'boolean' => [true], 'boolean key' => [[true]],
    'float key' => [[1.5]], 'control character' => [["a\0"]], 'nested' => [[['a']]], 'duplicate' => [['a', 'a']],
    'unknown' => [['ghost']], 'too many' => [['a', 'b']], 'associative' => [['key' => 'a']],
]);

it('saves ordered numeric keys canonically', function (): void {
    TestableDualListboxForm::$formSchema = [DualListboxField::make('values')->options([1 => 'One', 2 => 'Two'])];
    $component = Livewire::test(TestableDualListboxForm::class)->set('data.values', [2, '1']);
    expect($component->instance()->getSchema('form')->getState()['values'])->toBe(['2', '1']);
});

it('enforces required and exact count even for empty arrays', function (): void {
    foreach ([DualListboxField::make('values')->options(['a' => 'A'])->required(), DualListboxField::make('values')->options(['a' => 'A'])->exactItems(1)] as $field) {
        TestableDualListboxForm::$formSchema = [$field];
        $component = Livewire::test(TestableDualListboxForm::class)->set('data.values', []);
        expect(fn () => $component->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
    }
});

it('enforces minimum count and accepts a valid optional empty list', function (): void {
    TestableDualListboxForm::$formSchema = [DualListboxField::make('values')->options(['a' => 'A', 'b' => 'B'])->minItems(2)];
    $component = Livewire::test(TestableDualListboxForm::class)->set('data.values', ['a']);
    expect(fn () => $component->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
    $component->set('data.values', ['b', 'a']);
    expect($component->instance()->getSchema('form')->getState()['values'])->toBe(['b', 'a']);
    TestableDualListboxForm::$formSchema = [DualListboxField::make('values')->options(['a' => 'A'])];
    $component = Livewire::test(TestableDualListboxForm::class)->set('data.values', []);
    expect($component->instance()->getSchema('form')->getState()['values'])->toBe([]);
});
