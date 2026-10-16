<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Forms;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationResult;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\CompiledFormulaMap;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use InvalidArgumentException;

/** Schema-defined formulas share the same authoritative output lifecycle. */
final readonly class FormulaFields
{
    private CalculationSnapshots $snapshots;

    /** @var list<string> */
    private array $inputs;

    public function __construct(private CompiledFormulaMap $plan, private string $prefix = '')
    {
        $this->inputs = array_values(array_diff($plan->valueKeys(), $plan->computedKeys()));
        $this->snapshots = new CalculationSnapshots($plan, $prefix);
    }

    public function bindOutput(Field $field, string $key): void
    {
        CalculatedOutput::bind($field, $this->calculate(...), $key, $this->prefix, asString: true);
    }

    /** @param array<string, Component> $components */
    public function bind(array $components): void
    {
        foreach ($this->plan->computedKeys() as $key) {
            $field = $components[$key] ?? null;
            if (! $field instanceof Field) {
                throw new InvalidArgumentException("Missing calculated field [{$key}].");
            }
            $this->bindOutput($field, $key);
        }
        foreach ($this->inputs as $key) {
            $field = $components[$key] ?? null;
            if (! $field instanceof Field) {
                throw new InvalidArgumentException("Missing formula input [{$key}].");
            }
            $field->live()
                ->afterStateUpdated(fn (Get $get, Set $set, Field $component) => $this->update($get, $set, $component));
        }
    }

    private function update(Get $get, Set $set, Field $component): void
    {
        $result = $this->calculate($get, $component);
        $set->skipComponentsChildContainersWhileSearching(false);
        foreach ($this->plan->computedKeys() as $key) {
            $value = $result->values[$key] ?? null;
            $set($this->prefix.$key, $value !== null ? (string) $value : null);
        }
    }

    private function calculate(Get $get, Field $component): CalculationResult
    {
        return $this->snapshots->evaluateSchema($component->getContainer(), fn (): array => $this->read($get));
    }

    /** @return array<string, mixed> */
    private function read(Get $get): array
    {
        $get->skipComponentsChildContainersWhileSearching(false);
        $inputs = [];
        foreach ($this->inputs as $key) {
            $inputs[$key] = $get($this->prefix.$key);
        }

        return $inputs;
    }
}
