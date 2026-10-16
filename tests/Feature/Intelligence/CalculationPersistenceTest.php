<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\CalculatedFields;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;
use Bjanczak\FilamentFlexFields\Tests\Support\Intelligence\CalculationOrder;
use Bjanczak\FilamentFlexFields\Tests\Support\Intelligence\CalculationOrderFactory;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    Schema::create('fff_calculation_orders', function (Blueprint $table): void {
        $table->id();
        $table->integer('price')->default(0);
        $table->integer('quantity')->default(1);
        $table->integer('total')->default(0);
        $table->text('lines')->nullable();
    });
    Schema::create('fff_calculation_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('order_id')->constrained('fff_calculation_orders');
        $table->integer('price');
        $table->integer('quantity');
        $table->integer('total');
    });
});

function calculationPersistenceFields(): array
{
    $plan = FormulaEngine::plan(['total' => '{price}*{quantity}'], [
        'price' => new NumericInput(0, 1000000, integer: true),
        'quantity' => new NumericInput(1, 1000, integer: true),
    ]);

    return (new CalculatedFields($plan))->bind([
        'price' => FlexTextInput::make('price')->numeric()->default(120),
        'quantity' => FlexTextInput::make('quantity')->numeric()->default(4),
        'total' => FlexTextInput::make('total'),
    ]);
}

it('persists calculated results on create and edit instead of submitted outputs', function (): void {
    TestableTranslatableForm::$formSchema = calculationPersistenceFields();
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['price' => 120, 'quantity' => 4, 'total' => 1]);
    $form->set('data.total', 999999);
    $record = CalculationOrderFactory::new()->create($form->instance()->getSchema('form')->getState());
    expect($record->fresh()->total)->toBe(480);

    $record->update(['total' => 7]);
    $edit = Livewire::test(TestableTranslatableForm::class, ['record' => $record->fresh()])
        ->assertSet('data.total', 480)->set('data.quantity', 6)->assertSet('data.total', 720)
        ->set('data.total', 123456);
    $record->update($edit->instance()->getSchema('form')->getState());
    expect($record->fresh()->quantity)->toBe(6)->and($record->fresh()->total)->toBe(720);
    $edit->set('data.quantity', -1);
    expect(fn () => $record->update($edit->instance()->getSchema('form')->getState()))->toThrow(ValidationException::class);
    expect($record->fresh()->total)->toBe(720);
});

it('isolates repeater rows after update reorder removal and JSON round trip', function (): void {
    TestableTranslatableForm::$formSchema = [Repeater::make('lines')->schema(calculationPersistenceFields())->defaultItems(0)];
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['lines' => [
        ['price' => 10, 'quantity' => 2, 'total' => 999],
        ['price' => 20, 'quantity' => 3, 'total' => 888],
    ]]);
    [$first, $second] = array_keys($form->instance()->data['lines']);
    $form->set("data.lines.{$first}.quantity", 5)->assertSet("data.lines.{$first}.total", 50)->assertSet("data.lines.{$second}.total", 60);
    $form->set('data.lines', array_reverse($form->instance()->data['lines'], preserve_keys: true));
    $form->set("data.lines.{$second}.total", 1234);
    $state = $form->instance()->getSchema('form')->getState();
    expect(array_column($state['lines'], 'total'))->toBe([60, 50]);
    $record = CalculationOrderFactory::new()->create(['lines' => $state['lines']]);
    $edit = Livewire::test(TestableTranslatableForm::class, ['record' => $record->fresh()]);
    $items = $edit->instance()->data['lines'];
    array_pop($items);
    $edit->set('data.lines', $items);
    $record->update($edit->instance()->getSchema('form')->getState());
    expect(array_column($record->fresh()->lines, 'total'))->toBe([60]);
});

it('saves has-many repeater calculations on create and edit and rejects malformed rows', function (): void {
    TestableTranslatableForm::$formSchema = [Repeater::make('items')->relationship()->schema(calculationPersistenceFields())->defaultItems(0)];
    $record = CalculationOrderFactory::new()->create();
    $form = Livewire::test(TestableTranslatableForm::class, ['record' => $record])->set('data.items', [
        ['price' => 10, 'quantity' => 2, 'total' => 999],
        ['price' => 20, 'quantity' => 3, 'total' => 888],
    ]);
    $form->instance()->getSchema('form')->getState();
    expect($record->items()->orderBy('id')->pluck('total')->all())->toBe([20, 60]);
    $edit = Livewire::test(TestableTranslatableForm::class, ['record' => $record->fresh()]);
    $first = array_key_first($edit->instance()->data['items']);
    $edit->set("data.items.{$first}.quantity", 4)->set("data.items.{$first}.total", 777777);
    $edit->instance()->getSchema('form')->getState();
    expect($record->items()->orderBy('id')->pluck('total')->all())->toBe([40, 60]);
    $edit->set("data.items.{$first}.quantity", ['injected' => true]);
    expect(fn () => $edit->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
    expect($record->items()->orderBy('id')->pluck('total')->all())->toBe([40, 60]);
});

it('keeps custom update callbacks before and after binding and recalculates after input save hooks', function (): void {
    $plan = FormulaEngine::plan(['total' => '{price}*{quantity}'], [
        'price' => new NumericInput(0, 1000), 'quantity' => new NumericInput(1, 100, integer: true),
    ]);
    $events = [];
    $price = FlexTextInput::make('price')->numeric()
        ->afterStateUpdated(function () use (&$events): void {
            $events[] = 'before-binding';
        })
        ->beforeStateDehydrated(function (Set $set): void {
            $set('price', 30);
        }, shouldUpdateValidatedStateAfter: true);
    $fields = (new CalculatedFields($plan))->bind([
        'price' => $price, 'quantity' => FlexTextInput::make('quantity')->numeric(), 'total' => FlexTextInput::make('total'),
    ]);
    $price->afterStateUpdated(function () use (&$events): void {
        $events[] = 'after-binding';
    });
    TestableTranslatableForm::$formSchema = $fields;
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['price' => 10, 'quantity' => 2])
        ->set('data.price', 20)->assertSet('data.total', 40);
    expect($events)->toBe(['before-binding', 'after-binding']);
    $state = $form->instance()->getSchema('form')->getState();
    expect($state['price'])->toEqual(30)->and($state['total'])->toBe(60);
});

it('rejects runtime failures before any related row is updated or deleted', function (): void {
    $plan = FormulaEngine::plan(['total' => '{price}/{quantity}'], [
        'price' => new NumericInput(0, 1000), 'quantity' => new NumericInput(0, 100, integer: true),
    ]);
    $fields = (new CalculatedFields($plan))->bind([
        'price' => FlexTextInput::make('price')->numeric(),
        'quantity' => FlexTextInput::make('quantity')->numeric(),
        'total' => FlexTextInput::make('total'),
    ]);
    TestableTranslatableForm::$formSchema = [Repeater::make('items')->relationship()->schema($fields)->defaultItems(0)];
    $record = CalculationOrderFactory::new()->create();
    $first = $record->items()->create(['price' => 10, 'quantity' => 2, 'total' => 5]);
    $second = $record->items()->create(['price' => 20, 'quantity' => 4, 'total' => 5]);
    $removed = $record->items()->create(['price' => 30, 'quantity' => 3, 'total' => 10]);
    $form = Livewire::test(TestableTranslatableForm::class, ['record' => $record]);
    $rows = $form->instance()->data['items'];
    unset($rows['record-'.$removed->getKey()]);
    $rows['record-'.$first->getKey()]['price'] = 100;
    $rows['record-'.$second->getKey()]['quantity'] = 0;
    $form->set('data.items', $rows);
    expect(fn () => $form->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
    expect($first->fresh()->total)->toBe(5)
        ->and($second->fresh()->quantity)->toBe(4)
        ->and($removed->fresh())->not->toBeNull();
});

it('preserves input hydration and update callbacks inside a calculated schema', function (): void {
    $plan = FormulaEngine::plan(['total' => '{price}*{quantity}'], [
        'price' => new NumericInput(0, 1000), 'quantity' => new NumericInput(1, 100, integer: true),
    ]);
    $events = [];
    $price = FlexTextInput::make('price')->numeric()->default(10)
        ->afterStateHydrated(function (FlexTextInput $component) use (&$events): void {
            $events[] = 'hydrate';
            $component->state(15);
        });
    $group = (new CalculatedFields($plan))->schema([
        'total' => FlexTextInput::make('total'),
        'price' => $price,
        'quantity' => FlexTextInput::make('quantity')->numeric()->default(2),
    ])->statePath('invoice');
    $price->afterStateUpdated(function (Set $set) use (&$events): void {
        $events[] = 'update';
        $set('quantity', 3, shouldCallUpdatedHooks: true);
    });
    TestableTranslatableForm::$formSchema = [$group];
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', [
        'invoice' => ['price' => 10, 'quantity' => 2],
    ]);
    expect($form->instance()->data['invoice']['total'])->toBe(30)->and($events)->toBe(['hydrate']);
    $form->set('data.invoice.price', 20)->assertSet('data.invoice.total', 60);
    expect($form->instance()->getSchema('form')->getState()['invoice']['total'])->toBe(60)
        ->and($events)->toBe(['hydrate', 'update']);
});

it('creates a new parent before saving its calculated has-many rows', function (): void {
    TestableTranslatableForm::$formSchema = [Repeater::make('items')->relationship()->schema(calculationPersistenceFields())->defaultItems(0)];
    $form = Livewire::test(TestableTranslatableForm::class, ['record' => CalculationOrderFactory::new()->make()])
        ->set('data.items', [
            ['price' => 40, 'quantity' => 2, 'total' => 9999],
            ['price' => 50, 'quantity' => 3, 'total' => 9999],
        ]);
    $schema = $form->instance()->getSchema('form');
    $saved = null;
    $schema->getState(afterValidate: function (array $state) use ($schema, &$saved): void {
        $saved = CalculationOrderFactory::new()->create($state);
        $schema->model($saved);
    });
    expect($saved)->toBeInstanceOf(CalculationOrder::class)
        ->and($saved->items()->orderBy('id')->pluck('total')->all())->toBe([80, 150]);
});

it('isolates calculations in nested repeaters and reports errors without crashing their previews', function (): void {
    TestableTranslatableForm::$formSchema = [Repeater::make('groups')->schema([
        Repeater::make('lines')->schema(calculationPersistenceFields())->defaultItems(0),
    ])->defaultItems(0)];
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['groups' => [
        ['lines' => [['price' => 15, 'quantity' => 2]]],
        ['lines' => [['price' => 25, 'quantity' => 3]]],
    ]]);
    [$first, $second] = array_keys($form->instance()->data['groups']);
    $row = array_key_first($form->instance()->data['groups'][$first]['lines']);
    $form->set("data.groups.{$first}.lines.{$row}.quantity", 4);
    $state = $form->instance()->getSchema('form')->getState();
    expect($state['groups'][0]['lines'][0]['total'])->toBe(60)
        ->and($state['groups'][1]['lines'][0]['total'])->toBe(75);
    $form->set("data.groups.{$first}.lines.{$row}.quantity", 0)
        ->assertSet("data.groups.{$first}.lines.{$row}.total", null);
    try {
        $form->instance()->getSchema('form')->getState();
        test()->fail('An invalid nested row must block saving.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey("data.groups.{$first}.lines.{$row}.quantity");
    }
});
