<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\FormulaLaboratory;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\GraphLaboratory;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\PersistenceWorkflow;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\ScenarioFields;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\Scenarios;
use Bjanczak\FilamentFlexFields\Support\Playground\Intelligence\ValidationLaboratory;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;

/** Catalog composition only; calculations and presentation live in separate modules. */
final class FieldIntelligencePlayground
{
    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        $state = FormulaLaboratory::defaults() + ['intelligence__graph' => 'chain', 'intelligence__validation' => 'valid', 'intelligence__save__price' => 120, 'intelligence__save__quantity' => 4];
        foreach (Scenarios::all() as $scenario) {
            $state += $scenario->defaults();
        }

        return $state;
    }

    /** @return list<Component> */
    public function components(): array
    {
        return [
            View::make('filament-flex-fields::partials.playground.intelligence.overview'),
            FormulaLaboratory::section(),
            ...array_map(ScenarioFields::section(...), Scenarios::all()),
            PersistenceWorkflow::section(),
            GraphLaboratory::section(),
            ValidationLaboratory::section(),
        ];
    }
}
