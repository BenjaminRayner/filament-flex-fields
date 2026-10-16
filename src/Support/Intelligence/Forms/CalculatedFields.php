<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Forms;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationPlan;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationResult;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInputRule;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use InvalidArgumentException;

/** Bind a plan to actual fields. Persistence recalculates from validated inputs. */
final readonly class CalculatedFields
{
    private CalculationSnapshots $snapshots;

    public function __construct(public CalculationPlan $plan, public string $prefix = '')
    {
        if ($prefix !== '' && (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $prefix) || strlen($prefix) > 128)) {
            throw new InvalidArgumentException('Use a bounded flat field-name prefix. Nested schemas own their state path.');
        }
        $this->snapshots = new CalculationSnapshots($plan, $prefix);
    }

    /** @param array<string, Field> $fields
     * @return list<Field>
     */
    public function bind(array $fields): array
    {
        return $this->attach($fields, hydrateInputs: true);
    }

    /** Preserve input hydration callbacks by refreshing after the whole group hydrates.
     * @param  array<string, Field>  $fields
     */
    public function schema(array $fields): Group
    {
        return Group::make($this->attach($fields, hydrateInputs: false))
            ->afterStateHydrated(fn (Group $component) => $this->refreshGroup($component));
    }

    /** @param array<string, Field> $fields
     * @return list<Field>
     */
    private function attach(array $fields, bool $hydrateInputs): array
    {
        foreach (array_keys($this->plan->inputs + $this->plan->formulas) as $key) {
            if (! isset($fields[$key]) || ! $fields[$key] instanceof Field || $fields[$key]->getName() !== $this->prefix.$key) {
                throw new InvalidArgumentException("Missing or incorrectly named bound field [{$key}].");
            }
        }
        foreach ($this->plan->inputs as $key => $contract) {
            $fields[$key]->required($contract->required)->rules([new NumericInputRule($contract)])
                ->live(debounce: 250)
                ->afterStateUpdated(fn (Get $get, Set $set, Field $component) => $this->update($get, $set, $component));
            if ($hydrateInputs) {
                $fields[$key]->afterStateHydrated(fn (Get $get, Set $set, Field $component) => $this->update($get, $set, $component));
            }
        }
        foreach (array_keys($this->plan->formulas) as $key) {
            CalculatedOutput::bind($fields[$key], $this->calculate(...), $key, $this->prefix);
        }

        return array_values($fields);
    }

    private function refreshGroup(Group $group): void
    {
        $schema = $group->getChildSchema();
        if ($schema === null) {
            return;
        }
        foreach ($schema->getComponents() as $component) {
            if ($component instanceof Field) {
                $this->update($component->makeGetUtility(), $component->makeSetUtility(), $component);

                return;
            }
        }
    }

    private function update(Get $get, Set $set, Field $component): void
    {
        $result = $this->calculate($get, $component);
        $current = $component->getContainer()->getRawState();
        $set->skipComponentsChildContainersWhileSearching(false);
        foreach (array_keys($this->plan->formulas) as $output) {
            $value = $result->values[$output] ?? null;
            if (data_get($current, $this->prefix.$output) !== $value) {
                $set($this->prefix.$output, $value);
            }
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
        $values = [];
        foreach (array_keys($this->plan->inputs) as $key) {
            $values[$key] = $get($this->prefix.$key);
        }

        return $values;
    }
}
