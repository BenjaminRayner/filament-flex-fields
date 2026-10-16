<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Intelligence;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationPlan;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;

final readonly class ScenarioDefinition
{
    public CalculationPlan $plan;

    /**
     * @param  array<string, array{label: string, value: int|float, contract: NumericInput, suffix?: string, money?: bool}>  $inputs
     * @param  array<string, string>  $formulas
     * @param  array<string, array{label: string, format?: 'money'|'percent'|'number'|'boolean'}>  $outputs
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public array $inputs,
        array $formulas,
        public array $outputs,
    ) {
        $contracts = [];
        foreach ($inputs as $key => $input) {
            $contracts[$key] = $input['contract'];
        }
        $this->plan = new CalculationPlan($formulas, $contracts);
    }

    public function path(string $key): string
    {
        return 'intelligence__'.$this->id.'__'.$key;
    }

    /** @return array<string, int|float> */
    public function defaults(): array
    {
        $values = [];
        foreach ($this->inputs as $key => $input) {
            $values[$this->path($key)] = $input['value'];
        }

        return $values;
    }
}
