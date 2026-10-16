<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexChecklist;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexRadiolist;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTodoListForm;
use Livewire\Livewire;

beforeEach(function () {
    TestableTodoListForm::$formSchema = [
        FlexChecklist::make('permissions')->options(['a' => 'A', 'b' => 'B', 'locked' => 'Locked'])->disabledOptions(['locked'])->minSelections(1)->maxSelections(2),
        FlexRadiolist::make('delivery')->options(['standard' => 'Standard', 'express' => 'Express', 'locked' => 'Locked'])->disabledOptions(['locked'])->required(),
    ];
});

afterEach(function () {
    TestableTodoListForm::$formSchema = [];
});

it('renders independent boundaries and shared SSR rows with keyboard entry points', function () {
    $html = Livewire::test(TestableTodoListForm::class)->html();
    expect($html)->toContain('data-fff-asset-consumer="flex-checklist"', 'data-fff-asset-consumer="flex-radiolist"', 'data-choice-option', 'role="checkbox"', 'role="radio"', 'tabindex="0"')
        ->not->toContain('x-bind:checked', 'keydown.enter', 'toggle(@js');
});

it('validates and dehydrates both independent selections', function () {
    $form = Livewire::test(TestableTodoListForm::class)->set('data.permissions', ['a', 'b'])->set('data.delivery', 'express')->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['permissions' => ['a', 'b'], 'delivery' => 'express']);
});

it('does not wash away forged checklist values during hydration or casting', function (mixed $value) {
    $form = Livewire::test(TestableTodoListForm::class)->set('data.delivery', 'standard')->set('data.permissions', $value)->call('save')->assertHasErrors(['data.permissions']);
    expect($form->get('saved'))->toBeNull();
})->with([
    'nested' => [[['a']]], 'duplicates' => [['a', 'a']], 'unknown' => [['x']],
    'disabled' => [['locked']], 'map' => [['name' => 'a']], 'boolean' => [[true]], 'empty' => [[]],
]);

it('rejects forged radio values before saving', function (mixed $value) {
    $form = Livewire::test(TestableTodoListForm::class)->set('data.permissions', ['a'])->set('data.delivery', $value)->call('save')->assertHasErrors(['data.delivery']);
    expect($form->get('saved'))->toBeNull();
})->with(['array' => [['standard']], 'unknown' => ['x'], 'disabled' => ['locked'], 'bool' => [true], 'empty' => [null]]);

it('supports inherited RTL and per-field direction without dropping size styles', function () {
    TestableTodoListForm::$formSchema[0]->extraAttributes(['dir' => 'rtl', 'data-custom-list' => 'permissions']);
    TestableTodoListForm::$formSchema[1]->extraAttributes(['dir' => 'rtl']);
    $html = Livewire::test(TestableTodoListForm::class)->html();
    expect(substr_count($html, 'dir="rtl"'))->toBe(2)
        ->and($html)->toContain('data-custom-list="permissions"', '--fff-flex-checklist-label-size', '--fff-flex-radiolist-label-size');
});

it('saves numeric option identities without losing leading zeros or large integers', function () {
    TestableTodoListForm::$formSchema = [
        FlexChecklist::make('permissions')->options(['001' => 'Leading zero', 9007199254740993 => 'Large key']),
        FlexRadiolist::make('delivery')->options([9007199254740993 => 'Large key']),
    ];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.permissions', ['001', '9007199254740993'])->set('data.delivery', '9007199254740993')->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['permissions' => ['001', '9007199254740993'], 'delivery' => '9007199254740993']);
});
