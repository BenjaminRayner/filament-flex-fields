<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('parses formula and calculated keys from field definition arrays', function (): void {
    $fromTopLevel = FlexFieldDefinition::fromArray([
        'slug' => 'total',
        'label' => 'Total',
        'type' => FieldType::FlexTextInput->value,
        'formula' => '{price}*{qty}',
    ]);

    $fromConfig = FlexFieldDefinition::fromArray([
        'slug' => 'total',
        'label' => 'Total',
        'type' => FieldType::FlexTextInput->value,
        'config' => [
            'calculated' => '{price}+{tax}',
        ],
    ]);

    expect($fromTopLevel->formula)->toBe('{price}*{qty}')
        ->and($fromTopLevel->hasFormula())->toBeTrue()
        ->and($fromConfig->formula)->toBe('{price}+{tax}')
        ->and(FormulaEngine::fieldReferences('{price}*{qty}'))->toBe(['price', 'qty']);
});

it('disables formula target fields when a formula is configured', function (): void {
    $component = app(FlexFieldFormBuilder::class)->makeComponent(
        FlexFieldDefinition::fromArray([
            'slug' => 'total',
            'label' => 'Total',
            'type' => FieldType::FlexTextInput->value,
            'formula' => '{price}*{qty}',
        ]),
        '',
    );

    expect($component)->toBeInstanceOf(FlexTextInput::class)
        ->and($component->isDisabled())->toBeTrue();
});

it('updates formula target state when dependency fields change', function (): void {
    $components = (new FlexFieldFormBuilder)->build([
        FlexFieldDefinition::fromArray([
            'slug' => 'price',
            'label' => 'Price',
            'type' => FieldType::FlexTextInput->value,
            'default_value' => '12.5',
        ]),
        FlexFieldDefinition::fromArray([
            'slug' => 'qty',
            'label' => 'Qty',
            'type' => FieldType::FlexTextInput->value,
            'default_value' => '4',
        ]),
        FlexFieldDefinition::fromArray([
            'slug' => 'total',
            'label' => 'Total',
            'type' => FieldType::FlexTextInput->value,
            'formula' => '{price}*{qty}',
        ]),
    ], '');

    TestableTranslatableForm::$formSchema = $components;

    Livewire::test(TestableTranslatableForm::class)
        ->call('fillPlaygroundState', [
            'price' => '12.5',
            'qty' => '4',
            'total' => '',
        ])
        ->assertSet('data.total', '50')
        ->set('data.qty', '2')
        ->assertSet('data.total', '25');
});

it('updates chained formula targets when a seed dependency changes', function (): void {
    $components = (new FlexFieldFormBuilder)->build([
        FlexFieldDefinition::fromArray([
            'slug' => 'price',
            'label' => 'Price',
            'type' => FieldType::FlexTextInput->value,
            'default_value' => '10',
        ]),
        FlexFieldDefinition::fromArray([
            'slug' => 'qty',
            'label' => 'Qty',
            'type' => FieldType::FlexTextInput->value,
            'default_value' => '2',
        ]),
        FlexFieldDefinition::fromArray([
            'slug' => 'total',
            'label' => 'Total',
            'type' => FieldType::FlexTextInput->value,
            'formula' => '{price}*{qty}',
        ]),
        FlexFieldDefinition::fromArray([
            'slug' => 'tax',
            'label' => 'Tax',
            'type' => FieldType::FlexTextInput->value,
            'formula' => '{total}*pct(23)',
        ]),
    ], '');

    TestableTranslatableForm::$formSchema = $components;

    Livewire::test(TestableTranslatableForm::class)
        ->call('fillPlaygroundState', [
            'price' => '10',
            'qty' => '2',
            'total' => '',
            'tax' => '',
        ])
        ->assertSet('data.total', '20')
        ->assertSet('data.tax', '4.6')
        ->set('data.qty', '5')
        ->assertSet('data.total', '50')
        ->assertSet('data.tax', '11.5');
});

it('recalculates the complete schema graph during saving and rejects invalid input', function (): void {
    $definitions = [
        ['slug' => 'price', 'validation' => ['numeric', 'min:0']],
        ['slug' => 'qty', 'validation' => ['integer', 'min:1']],
        ['slug' => 'subtotal', 'formula' => '{price}*{qty}'],
        ['slug' => 'total', 'formula' => '{subtotal}+10'],
    ];
    $components = (new FlexFieldFormBuilder)->build(array_map(
        fn (array $definition): FlexFieldDefinition => FlexFieldDefinition::fromArray($definition + [
            'label' => ucfirst($definition['slug']), 'type' => FieldType::FlexTextInput->value,
        ]), $definitions,
    ), '');
    TestableTranslatableForm::$formSchema = $components;
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', [
        'price' => '10', 'qty' => '2', 'subtotal' => '999999', 'total' => '-1',
    ])->assertSet('data.subtotal', '20')->assertSet('data.total', '30');
    $form->set('data.subtotal', '88888')->set('data.total', '77777');
    $state = $form->instance()->getSchema('form')->getState();
    expect($state['subtotal'])->toBe('20')->and($state['total'])->toBe('30');
    $form->set('data.price', 'not numeric')->assertSet('data.total', null);
    expect(fn () => $form->instance()->getSchema('form')->getState())
        ->toThrow(ValidationException::class);
});

it('preserves prefix isolation in schema-defined calculations', function (): void {
    $definitions = [
        FlexFieldDefinition::fromArray(['slug' => 'price', 'label' => 'Price', 'type' => FieldType::FlexTextInput->value]),
        FlexFieldDefinition::fromArray(['slug' => 'qty', 'label' => 'Quantity', 'type' => FieldType::FlexTextInput->value]),
        FlexFieldDefinition::fromArray(['slug' => 'total', 'label' => 'Total', 'type' => FieldType::FlexTextInput->value, 'formula' => '{price}*{qty}']),
    ];
    $builder = new FlexFieldFormBuilder;
    TestableTranslatableForm::$formSchema = [...$builder->build($definitions, 'first'), ...$builder->build($definitions, 'second')];
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', [
        'first' => ['price' => '10', 'qty' => '2'], 'second' => ['price' => '20', 'qty' => '3'],
    ])->set('data.first.qty', '4')->assertSet('data.first.total', '40')->assertSet('data.second.total', '60');
    $form->set('data.second.total', '12345');
    $state = $form->instance()->getSchema('form')->getState();
    expect($state['first']['total'])->toBe('40')->and($state['second']['total'])->toBe('60');
});

it('keeps a currency input own hydration callback when wiring calculated outputs', function (): void {
    $definitions = [
        FlexFieldDefinition::fromArray(['slug' => 'price', 'label' => 'Price', 'type' => FieldType::Currency->value, 'config' => ['currency' => 'EUR']]),
        FlexFieldDefinition::fromArray(['slug' => 'total', 'label' => 'Total', 'type' => FieldType::FlexTextInput->value, 'formula' => '{price}/100']),
    ];
    $builder = new FlexFieldFormBuilder;
    $components = [];
    foreach ($definitions as $definition) {
        $components[$definition->slug] = $builder->makeComponent($definition, '');
    }
    $components['price']->afterStateHydrated(fn ($component) => $component->state(1500));
    $builder->wireFormulaFields($definitions, $components, '');
    TestableTranslatableForm::$formSchema = array_values($components);
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['price' => 1000, 'total' => '999']);
    $form->assertSet('data.price', 1500)->assertSet('data.total', '15');
    expect($form->instance()->getSchema('form')->getState()['total'])->toBe('15');
});
