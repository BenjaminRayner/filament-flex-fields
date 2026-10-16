<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\CalculatedFields;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\CalculationSnapshots;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Schemas\Schema;

it('evaluates the maximum graph repeatedly without carrying values between jobs', function (): void {
    $formulas = [];
    for ($i = 0; $i < 512; $i++) {
        $formulas['result_'.$i] = $i === 0 ? '{input}+1' : '{result_'.($i - 1).'}+1';
    }
    $plan = FormulaEngine::plan($formulas, ['input' => new NumericInput(integer: true)]);
    $plan->evaluate(['input' => 0]);
    $memory = memory_get_usage();
    $samples = [];
    for ($job = 0; $job < 2000; $job++) {
        $start = hrtime(true);
        $result = $plan->evaluate(['input' => $job, 'result_511' => -999]);
        $samples[] = (hrtime(true) - $start) / 1e6;
        if (! $result->successful() || $result->values['result_511'] !== $job + 512) {
            test()->fail('A graph result leaked or failed between jobs.');
        }
    }
    sort($samples);
    $growth = memory_get_usage() - $memory;
    expect($growth)->toBeLessThan(2 * 1024 * 1024);
    fwrite(STDERR, sprintf("\n512-output graph, 2000 jobs: median %.3f ms, p95 %.3f ms; memory growth %d bytes\n", $samples[1000], $samples[1900], $growth));
});

it('releases per-owner snapshots and never shares values across schemas', function (): void {
    $plan = FormulaEngine::plan(['total' => '{input}*2'], ['input' => new NumericInput(integer: true)]);
    $snapshots = new CalculationSnapshots($plan);
    $first = Schema::make();
    $second = Schema::make();
    $value = $snapshots->evaluate($first, ['input' => 10]);
    expect($snapshots->evaluate($first, ['input' => 10]))->toBe($value)
        ->and($snapshots->evaluate($second, ['input' => 10]))->not->toBe($value)
        ->and($snapshots->evaluate($first, ['input' => 11])->values)->toBe(['total' => 22]);
    $owners = [];
    for ($i = 0; $i < 1000; $i++) {
        $owner = Schema::make();
        $owners[] = WeakReference::create($owner);
        expect($snapshots->evaluate($owner, ['input' => $i])->values['total'])->toBe($i * 2);
        unset($owner);
    }
    gc_collect_cycles();
    expect(array_filter($owners, fn (WeakReference $owner) => $owner->get() !== null))->toBe([]);
});

it('dehydrates a full 512-output form and replaces every submitted calculated value', function (int $inputCount, bool $grouped): void {
    $formulas = [];
    $fields = $inputs = $forged = [];
    for ($i = 0; $i < $inputCount; $i++) {
        $key = 'input_'.$i;
        $fields[$key] = FlexTextInput::make($key)->numeric()->default(100);
        $inputs[$key] = new NumericInput(integer: true);
        $forged[$key] = 100;
    }
    for ($i = 0; $i < 512; $i++) {
        $key = 'result_'.$i;
        $formulas[$key] = '{input_'.($i % $inputCount).'}+'.$i;
        $fields[$key] = FlexTextInput::make($key);
        $forged[$key] = -999;
    }
    $plan = FormulaEngine::plan($formulas, $inputs);
    $binding = new CalculatedFields($plan);
    TestableTranslatableForm::$formSchema = $grouped ? [$binding->schema($fields)] : $binding->bind($fields);
    $form = new TestableTranslatableForm;
    $schema = $form->getSchema('form');
    $fillStart = hrtime(true);
    $schema->fill($forged);
    $fillMs = (hrtime(true) - $fillStart) / 1e6;
    $samples = [];
    for ($run = 0; $run < 10; $run++) {
        $form->data = $forged;
        $start = hrtime(true);
        $state = $schema->getState();
        $samples[] = (hrtime(true) - $start) / 1e6;
        expect($state['result_0'])->toBe(100)->and($state['result_511'])->toBe(611);
    }
    sort($samples);
    fwrite(STDERR, sprintf("\n%d-input / 512-output %s: fill %.3f ms; validation/dehydration median %.3f ms, max %.3f ms\n", $inputCount, $grouped ? 'schema' : 'bind', $fillMs, $samples[5], max($samples)));
})->with([[1, false], [128, false], [512, false], [512, true]]);

it('invalidates cached typed reads on state changes and refuses owner-retaining values', function (): void {
    $plan = FormulaEngine::plan(['total' => '{input}*2'], ['input' => new NumericInput(integer: true)]);
    $snapshots = new CalculationSnapshots($plan);
    $form = new TestableTranslatableForm;
    $form->data = ['input' => 5];
    $schema = Schema::make()->livewire($form)->statePath('data');
    $reads = 0;
    $read = function () use ($form, &$reads): array {
        $reads++;

        return ['input' => $form->data['input']];
    };
    expect($snapshots->evaluateSchema($schema, $read)->values)->toBe(['total' => 10]);
    $snapshots->evaluateSchema($schema, $read);
    expect($reads)->toBe(1);
    $form->data['input'] = 6;
    expect($snapshots->evaluateSchema($schema, $read)->values)->toBe(['total' => 12])->and($reads)->toBe(2);
    $form->data['unrelated'] = $schema;
    $snapshots->evaluateSchema($schema, $read);
    $snapshots->evaluateSchema($schema, $read);
    expect($reads)->toBe(4);
    $weak = WeakReference::create($schema);
    unset($schema, $form->data['unrelated']);
    gc_collect_cycles();
    expect($weak->get())->toBeNull();
});
