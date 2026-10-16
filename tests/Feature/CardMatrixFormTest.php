<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCards;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCheckboxCards;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMatrixTable;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ImageChoiceCards;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MatrixChoiceField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTodoListForm;
use Livewire\Livewire;

beforeEach(function () {
    TestableTodoListForm::$formSchema = [];
});
afterEach(function () {
    TestableTodoListForm::$formSchema = [];
});

it('renders the card family through shared templates with independent consumer identities', function () {
    TestableTodoListForm::$formSchema = [
        ChoiceCards::make('plan')->options(['a' => ['label' => 'A', 'price' => '$10']])->default('a'),
        ChoiceCheckboxCards::make('features')->options(['a' => 'A'])->default(['a']),
        ImageChoiceCards::make('cover')->options(['a' => ['label' => 'A', 'image' => '/image.webp']])->default('a'),
    ];
    $html = Livewire::test(TestableTodoListForm::class)->html();
    expect($html)->toContain('data-fff-asset-consumer="choice-cards"', 'data-fff-asset-consumer="choice-checkbox-cards"', 'data-fff-asset-consumer="image-choice-cards"', 'data-card-config', '$10', '/image.webp')
        ->not->toContain('x-init="init()"', 'x-bind:checked');
});

it('saves a valid selection for every card mode', function (string $class, bool $multiple) {
    $field = $class::make('value')->options(['001' => 'Leading zero', 9007199254740993 => 'Large key']);
    if ($field instanceof ImageChoiceCards) {
        $field->multiple($multiple);
    }
    TestableTodoListForm::$formSchema = [$field];
    $value = $multiple ? ['001', '9007199254740993'] : '9007199254740993';
    $form = Livewire::test(TestableTodoListForm::class)->set('data.value', $value)->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['value' => $value]);
})->with([[ChoiceCards::class, false], [ChoiceCheckboxCards::class, true], [ImageChoiceCards::class, false], [ImageChoiceCards::class, true]]);

it('rejects forged card selections through the real form save boundary', function (string $class, bool $multiple, mixed $payload) {
    $field = $class::make('value')->options(['a' => 'A', 'locked' => ['label' => 'Locked', 'disabled' => true]]);
    if ($field instanceof ImageChoiceCards) {
        $field->multiple($multiple);
    }
    TestableTodoListForm::$formSchema = [$field];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.value', $payload)->call('save')->assertHasErrors(['data.value']);
    expect($form->get('saved'))->toBeNull();
})->with([
    [ChoiceCards::class, false, ['a']], [ChoiceCards::class, false, true], [ChoiceCards::class, false, 'locked'],
    [ChoiceCheckboxCards::class, true, ['a', 'a']], [ChoiceCheckboxCards::class, true, [['a']]], [ChoiceCheckboxCards::class, true, ['name' => 'a']], [ChoiceCheckboxCards::class, true, ['locked']],
    [ImageChoiceCards::class, false, ['a']], [ImageChoiceCards::class, false, true], [ImageChoiceCards::class, false, 'locked'],
    [ImageChoiceCards::class, true, ['a', 'a']], [ImageChoiceCards::class, true, [false]], [ImageChoiceCards::class, true, ['locked']],
]);

it('validates matrix data without silently repairing forged payloads', function (bool $multiple, mixed $payload) {
    TestableTodoListForm::$formSchema = [MatrixChoiceField::make('value')->mode($multiple ? 'checkbox' : 'radio')->rows(['r' => ['label' => 'Row', 'max' => 1], 'locked' => ['label' => 'Locked', 'disabled' => true]])->matrixColumns(['a' => 'A', 'b' => 'B', 'locked' => ['label' => 'Locked', 'disabled' => true]])];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.value', $payload)->call('save')->assertHasErrors(['data.value']);
    expect($form->get('saved'))->toBeNull();
})->with([
    [false, ['r' => true]], [false, ['r' => ['a']]], [false, ['r' => 'unknown']], [false, ['r' => 'locked']], [false, ['locked' => 'a']], [false, ['unknown' => 'a']],
    [true, ['r' => ['a', 'a']]], [true, ['r' => 'a']], [true, ['r' => ['a', 'b']]], [true, ['r' => [[1]]]], [true, ['r' => ['name' => 'a']]], [true, ['locked' => ['a']]],
]);

it('saves both matrix modes and rejects forged conditional dependencies', function () {
    TestableTodoListForm::$formSchema = [MatrixChoiceField::make('value')->mode('checkbox')->rows(['r' => 'Row', 'other' => 'Other'])->matrixColumns(['a' => 'A', 'b' => 'B'])->disableCellWhen('other', 'b', 'r', 'a')];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.value', ['r' => ['a'], 'other' => ['a']])->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['value' => ['r' => ['a'], 'other' => ['a']]]);
    $form->set('saved', null)->set('data.value', ['r' => ['a'], 'other' => ['b']])->call('save')->assertHasErrors(['data.value']);
    expect($form->get('saved'))->toBeNull();
});

it('preserves per-field direction and configured presentation tokens', function () {
    TestableTodoListForm::$formSchema = [ChoiceCards::make('card')->options(['a' => 'A'])->extraAttributes(['dir' => 'rtl']), MatrixChoiceField::make('grid')->rows(['r' => 'Row'])->matrixColumns(['a' => 'A'])->extraAttributes(['dir' => 'rtl'])];
    $html = Livewire::test(TestableTodoListForm::class)->html();
    expect(substr_count($html, 'dir="rtl"'))->toBe(2);
    expect($html)->toContain('--fff-choice-cards-p: 1rem;', '--fff-matrix-choice-radius: 1rem;', '--fff-matrix-choice-columns: 1;');
});

it('validates actual matrix table child fields and retains named row storage', function () {
    TestableTodoListForm::$formSchema = [FlexMatrixTable::make('specs')->rows(['r' => 'Row'])->schema([FlexTextInput::make('value')->required()->numeric()->minValue(0)])];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.specs', ['r' => ['value' => '12']])->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['specs' => ['r' => ['value' => 12.0]]]);
    $form->set('saved', null)->set('data.specs.r.value', '-1')->call('save')->assertHasErrors(['data.specs.r.value']);
    expect($form->get('saved'))->toBeNull();
});

it('rejects additional matrix table rows', function () {
    TestableTodoListForm::$formSchema = [FlexMatrixTable::make('specs')->rows(['r' => 'Row'])->schema([FlexTextInput::make('value')])];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.specs', ['r' => ['value' => '12'], 'forged' => ['value' => '99']])->call('save')->assertHasErrors(['data.specs']);
    expect($form->get('saved'))->toBeNull();
});

it('rejects malformed matrix table payloads without a rendering exception', function (mixed $payload) {
    TestableTodoListForm::$formSchema = [FlexMatrixTable::make('specs')->rows(['r' => 'Row'])->schema([FlexTextInput::make('value')])];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.specs', $payload)->call('save')->assertHasErrors(['data.specs']);
    expect($form->get('saved'))->toBeNull();
})->with(['scalar' => ['bad'], 'nested scalar' => [['r' => 'bad']]]);

it('does not save values of disabled matrix table child schemas', function () {
    TestableTodoListForm::$formSchema = [FlexMatrixTable::make('specs')->rows(['r' => 'Row', 'locked' => ['label' => 'Locked', 'disabled' => true]])->schema([FlexTextInput::make('value')])];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.specs', ['r' => ['value' => '12'], 'locked' => ['value' => 'forged']])->call('save')->assertHasNoErrors();
    expect($form->get('saved')['specs']['locked'] ?? [])->not->toHaveKey('value');
});

it('renders identical default check indicator markup in both card families while retaining radio semantics', function () {
    TestableTodoListForm::$formSchema = [
        ChoiceCards::make('single')->options(['a' => 'A', 'b' => 'B'])->default('a'),
        ChoiceCheckboxCards::make('multiple')->indicator('check')->options(['a' => 'A', 'b' => 'B'])->default(['a']),
    ];
    $html = Livewire::test(TestableTodoListForm::class)->html();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $nodes = (new DOMXPath($document))->query('//span[@class="fff-choice-cards__indicator"]');
    expect($nodes->length)->toBe(4)
        ->and($document->saveHTML($nodes->item(0)))->toBe($document->saveHTML($nodes->item(2)))
        ->and($html)->toContain('role="radiogroup"', 'type="radio"', 'type="checkbox"', 'viewBox="2 2 16 16"');
});
