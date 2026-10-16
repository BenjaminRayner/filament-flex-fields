<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Forms;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationPlan;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationResult;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\CompiledFormulaMap;
use Closure;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use WeakMap;

/** One current result per live schema; destroying a row releases its values. */
final class CalculationSnapshots
{
    /** @var WeakMap<Schema, array{inputs: array<string, mixed>, result: CalculationResult}> */
    private WeakMap $owners;

    /** @var WeakMap<Schema, array{state: array<array-key, mixed>, result: CalculationResult}> */
    private WeakMap $states;

    /** @var list<string> */
    private readonly array $outputPaths;

    /** @var array<string, true> */
    private readonly array $outputKeys;

    public function __construct(private readonly CalculationPlan|CompiledFormulaMap $plan, private readonly string $prefix = '')
    {
        $this->owners = new WeakMap;
        $this->states = new WeakMap;
        $outputs = $plan instanceof CalculationPlan ? array_keys($plan->formulas) : $plan->computedKeys();
        $this->outputPaths = array_map(fn (string $key): string => $prefix.$key, $outputs);
        $this->outputKeys = array_fill_keys($this->outputPaths, true);
    }

    /** Reuse typed reads while owner state, excluding untrusted outputs, is unchanged.
     * @param  Closure(): array<string, mixed>  $read
     */
    public function evaluateSchema(Schema $owner, Closure $read): CalculationResult
    {
        $state = $owner->getRawState();
        if (is_array($state)) {
            $state = str_contains($this->prefix, '.')
                ? Arr::except($state, $this->outputPaths)
                : array_diff_key($state, $this->outputKeys);
        }
        $previous = $this->states[$owner] ?? null;
        if ($previous !== null && $previous['state'] === $state) {
            return $previous['result'];
        }

        $result = $this->evaluate($owner, $read());
        if (is_array($state) && $this->cacheable($state)) {
            $this->states[$owner] = ['state' => $state, 'result' => $result];
        } else {
            unset($this->states[$owner]);
        }

        return $result;
    }

    /** @param array<string, mixed> $inputs */
    public function evaluate(Schema $owner, array $inputs): CalculationResult
    {
        $previous = $this->owners[$owner] ?? null;
        if ($previous !== null && $previous['inputs'] === $inputs) {
            return $previous['result'];
        }

        try {
            $result = $this->plan instanceof CalculationPlan
                ? $this->plan->evaluate($inputs)
                : new CalculationResult($this->plan->evaluate($inputs), evaluated: $this->plan->computedKeys());
        } catch (InvalidArgumentException $exception) {
            $result = new CalculationResult([], ['calculation' => $exception->getMessage()]);
        }
        if ($this->cacheable($inputs)) {
            $this->owners[$owner] = ['inputs' => $inputs, 'result' => $result];
        } else {
            unset($this->owners[$owner]);
        }

        return $result;
    }

    /** Objects and unbounded/cyclic arrays must not retain an owner through its value. */
    private function cacheable(mixed $value, int &$remaining = 4096, int $depth = 0): bool
    {
        if (--$remaining < 0 || $depth > 32) {
            return false;
        }
        if (! is_array($value)) {
            return $value === null || is_scalar($value);
        }
        foreach ($value as $child) {
            if (! $this->cacheable($child, $remaining, $depth + 1)) {
                return false;
            }
        }

        return true;
    }
}
