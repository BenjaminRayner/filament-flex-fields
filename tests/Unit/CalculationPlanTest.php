<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationPlan;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\CalculatedFields;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;
use Bjanczak\FilamentFlexFields\Support\Playground\FieldIntelligencePlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\FormulaLaboratory;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\GraphLaboratory;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\Scenarios;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\ValidationLaboratory;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Schemas\Components\Group;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('validates inputs and publishes only a complete calculated result', function (): void {
    $plan = new CalculationPlan(['subtotal' => '{price}*{quantity}', 'total' => '{subtotal}+10'], [
        'price' => new NumericInput(0, 10000), 'quantity' => new NumericInput(1, 100, integer: true),
    ]);
    $result = $plan->evaluate(['price' => 12.5, 'quantity' => 4, 'subtotal' => 999999, 'total' => -100]);
    expect($result->validated())->toBe(['subtotal' => 50, 'total' => 60])
        ->and($result->evaluated)->toBe(['subtotal', 'total'])
        ->and($plan->dependencies())->toBe(['subtotal' => ['price', 'quantity'], 'total' => ['subtotal']]);

    $invalid = $plan->evaluate(['price' => 'invalid', 'quantity' => 101]);
    expect($invalid->values)->toBe([])->and($invalid->errors)->toHaveKeys(['price', 'quantity'])
        ->and(fn () => $invalid->validated('data.'))->toThrow(ValidationException::class);
});

it('rejects forged numeric values without turning them into zero', function (mixed $value): void {
    $plan = new CalculationPlan(['total' => '{input}*2'], ['input' => new NumericInput(0, 100, integer: true)]);
    $result = $plan->evaluate(['input' => $value]);
    expect($result->successful())->toBeFalse()->and($result->values)->toBe([])->and($result->errors)->toHaveKey('input');
})->with([null, '', 'garbage', -1, 101, 1.5, true, false, [['amount' => 5]], INF, -INF, NAN, '1e999']);

it('supports an explicit optional default but keeps zero as a real value', function (): void {
    $plan = new CalculationPlan(['result' => '{value}+1'], ['value' => new NumericInput(0, 10, integer: true, required: false, default: 5)]);
    expect($plan->evaluate([])->validated())->toBe(['result' => 6])
        ->and($plan->evaluate(['value' => 0])->validated())->toBe(['result' => 1]);
});

it('rejects undeclared references, unsafe syntax, invalid names and excluded functions', function (): void {
    expect(fn () => new CalculationPlan(['total' => '{undeclared}+1'], []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CalculationPlan(['total' => 'system(1)'], []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CalculationPlan(['bad key' => '1'], []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CalculationPlan(['total' => 'pct({value})'], ['value' => new NumericInput], ['round']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CalculationPlan(['total' => '1'], [], ['system']))->toThrow(InvalidArgumentException::class);
});

it('validates signatures in skipped branches before running a plan', function (): void {
    expect(FormulaEngine::validate('if(1,20,pct(1,2))'))->not->toBeEmpty()
        ->and(FormulaEngine::validate('if(1,20,10/0)'))->toBe([])
        ->and(FormulaEngine::evaluate('if(1,20,10/0)', []))->toBe(20);
});

it('never exposes partial results after a later output fails', function (): void {
    $plan = new CalculationPlan(['first' => '{input}*2', 'second' => '{first}/0'], ['input' => new NumericInput]);
    $result = $plan->evaluate(['input' => 20]);
    expect($result->successful())->toBeFalse()->and($result->values)->toBe([])->and($result->errors)->toHaveKey('calculation');
});

it('rejects overflowing intermediates even if a comparison would hide them', function (): void {
    $overflow = str_repeat('pow(999999999999,12)*', 3).'pow(999999999999,12)';
    expect(fn () => FormulaEngine::evaluate('('.$overflow.')>0', []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FormulaEngine::moneyMinor(INF))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FormulaEngine::moneyMinor(PHP_INT_MAX))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FormulaEngine::toNumber('1e999'))->toThrow(InvalidArgumentException::class)
        ->and(FormulaEngine::evaluate('trunc(100000000000000000000)', []))->toBe(1.0e20);
});

it('identifies all cycle members without labelling dependent outputs as cyclic', function (): void {
    expect(FormulaEngine::detectCycle(['a' => '{b}', 'b' => '{c}', 'c' => '{a}', 'dependent' => '{a}+1']))->toBe(['a', 'b', 'c'])
        ->and(FormulaEngine::detectCycle(['a' => '{a}']))->toBe(['a'])
        ->and(FormulaEngine::detectCycle(['a' => '{b}+{c}', 'b' => '{a}', 'c' => '{b}', 'dependent' => '{a}']))->toBe(['a', 'b', 'c'])
        ->and(FormulaEngine::detectCycle(['a' => '{b}', 'b' => '{a}', 'c' => '{d}', 'd' => '{c}']))->toBe(['a', 'b', 'c', 'd'])
        ->and(fn () => FormulaEngine::compileMap(['a' => '{b}', 'b' => '{a}']))->toThrow(InvalidArgumentException::class);
});

it('updates only dependent branches and calculates fully without a complete baseline', function (): void {
    $compiled = FormulaEngine::compileMap(['total' => '{price}*{quantity}', 'progress' => '{done}*2']);
    $inputs = ['price' => 10, 'quantity' => 2, 'done' => 5];
    $previous = $compiled->evaluate($inputs);
    expect($compiled->evaluate(['price' => 20] + $inputs + $previous, ['price']))->toBe(['total' => 40])
        ->and($compiled->evaluate($inputs, ['price']))->toBe(['total' => 20, 'progress' => 10])
        ->and(fn () => $compiled->evaluate($inputs + $previous, ['unknown']))->toThrow(InvalidArgumentException::class);
});

it('calculates every published scenario from its declared defaults', function (): void {
    foreach (Scenarios::all() as $scenario) {
        $inputs = array_map(fn (array $input) => $input['value'], $scenario->inputs);
        $result = $scenario->plan->evaluate($inputs);
        expect($result->successful())->toBeTrue()->and(array_keys($result->values))->toBe(array_keys($scenario->outputs));
        expect(view('filament-flex-fields::partials.playground.intelligence.results', compact('scenario', 'result'))->render())->toContain('Calculation ready');
    }
});

it('reports invalid laboratory payloads and preserves short circuit behavior', function (): void {
    expect(FormulaLaboratory::inspect('if({b}>0,{a}/{b},0)', ['a' => 120, 'b' => 0, 'c' => 15], 'standard')['result']->validated())->toBe(['result' => 0])
        ->and(FormulaLaboratory::inspect('{unknown}', ['a' => 1, 'b' => 2, 'c' => 3], 'standard')['result']->successful())->toBeFalse()
        ->and(FormulaLaboratory::inspect('pct({a})', ['a' => 1, 'b' => 2, 'c' => 3], 'restricted')['result']->successful())->toBeFalse()
        ->and(GraphLaboratory::inspect('cycle')['cycle'])->toBe(['a', 'b', 'c'])
        ->and(GraphLaboratory::inspect('branches')['changed'])->toBe(['total' => 600]);
    foreach (array_keys(ValidationLaboratory::options()) as $case) {
        expect(ValidationLaboratory::inspect($case)['result']->successful())->toBe(in_array($case, ['valid', 'forged'], true));
    }
});

it('binds to real fields and recalculates a forged output during dehydration', function (): void {
    $plan = new CalculationPlan(['total' => '{price}*{quantity}'], ['price' => new NumericInput(0, 10000), 'quantity' => new NumericInput(1, 100, integer: true)]);
    TestableTranslatableForm::$formSchema = (new CalculatedFields($plan))->bind([
        'price' => FlexTextInput::make('price')->numeric()->default(10),
        'quantity' => FlexTextInput::make('quantity')->numeric()->default(2),
        'total' => FlexTextInput::make('total'),
    ]);
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['price' => 10, 'quantity' => 2, 'total' => 999]);
    $form->assertSet('data.total', 20)->set('data.quantity', 3)->assertSet('data.total', 30);
    $form->set('data.total', 99999);
    expect($form->instance()->getSchema('form')->getState()['total'])->toBe(30);
    $form->set('data.quantity', -1)->assertSet('data.total', null);
    expect(fn () => $form->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
});

it('renders the entire rebuilt workspace with its initial state', function (): void {
    $playground = new FieldIntelligencePlayground;
    TestableTranslatableForm::$formSchema = $playground->components();
    Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', $playground->defaultState())
        ->assertSee('Inputs become answers.')->assertSee('Invoice & tax')->assertSee('Calculation ready')
        ->assertSee('Validation & tamper protection')->set('data.intelligence__invoice__quantity', 0)
        ->assertSee('Minimum value: 1.');
});

it('hydrates defaults regardless of the bound fields presentation order', function (): void {
    $plan = FormulaEngine::plan(['total' => '{price}*{quantity}'], [
        'price' => new NumericInput(0, 10000), 'quantity' => new NumericInput(1, 100, integer: true),
    ]);
    TestableTranslatableForm::$formSchema = (new CalculatedFields($plan))->bind([
        'total' => FlexTextInput::make('total'),
        'price' => FlexTextInput::make('price')->numeric()->default(10),
        'quantity' => FlexTextInput::make('quantity')->numeric()->default(2),
    ]);
    $form = Livewire::test(TestableTranslatableForm::class);
    $form->instance()->getSchema('form')->fill();
    expect($form->instance()->data['total'])->toBe(20);
});

it('isolates nested bindings and reports runtime failures at the actual field path', function (): void {
    $plan = FormulaEngine::plan(['total' => '{price}/{quantity}'], [
        'price' => new NumericInput(0, 10000), 'quantity' => new NumericInput(0, 100, integer: true),
    ]);
    $group = fn (string $path) => Group::make((new CalculatedFields($plan))->bind([
        'price' => FlexTextInput::make('price')->numeric(),
        'quantity' => FlexTextInput::make('quantity')->numeric(),
        'total' => FlexTextInput::make('total'),
    ]))->statePath($path);
    TestableTranslatableForm::$formSchema = [$group('first'), $group('second')];
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', [
        'first' => ['price' => 120, 'quantity' => 4],
        'second' => ['price' => 50, 'quantity' => 2],
    ])->set('data.first.price', 160)->assertSet('data.first.total', 40)->assertSet('data.second.total', 25);
    expect($form->instance()->getSchema('form')->getState()['first']['total'])->toBe(40);
    $form->set('data.first.quantity', 0)->assertSet('data.first.total', null);
    try {
        $form->instance()->getSchema('form')->getState();
        test()->fail('A failed calculation must block persistence.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.first.total');
    }
});

it('rejects integer and money values that would silently lose whole-number precision', function (): void {
    $input = new NumericInput(integer: true);
    expect($input->validate('9007199254740991'))->toBe(9007199254740991.0);
    foreach (['9007199254740992', '9007199254740993', '-9007199254740993', '1e20'] as $value) {
        expect(fn () => $input->validate($value))->toThrow(InvalidArgumentException::class);
        expect(fn () => FormulaEngine::moneyMajor($value))->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => FormulaEngine::moneyMinor(1e15))->toThrow(InvalidArgumentException::class);
    expect(FormulaEngine::moneyMinor(12.34))->toBe(1234);
});

it('checks fractional text before floating-point rounding can turn it into an integer', function (): void {
    $input = new NumericInput(integer: true);
    foreach (['1.00000000000000001', '0.99999999999999999', '1e-1000', '12.5', str_repeat('0', 257)] as $value) {
        expect(fn () => $input->validate($value))->toThrow(InvalidArgumentException::class);
    }
    foreach (['1.000', '1e2', '100e-2', '-10.00', '0e-1000'] as $value) {
        expect($input->validate($value))->toBe((float) $value);
    }
});
