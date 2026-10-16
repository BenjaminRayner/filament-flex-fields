<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Intelligence;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInputRule;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;

final class ScenarioFields
{
    public static function section(ScenarioDefinition $scenario): Section
    {
        $fields = [];
        foreach ($scenario->inputs as $key => $input) {
            $contract = $input['contract'];
            $field = ($input['money'] ?? false)
                ? CurrencyField::make($scenario->path($key))->currency('EUR')
                : NumberStepper::make($scenario->path($key))->minValue($contract->min)->maxValue($contract->max)->step($contract->integer ? 1 : 0.5)->suffix($input['suffix'] ?? null);
            $fields[] = $field->label($input['label'])->default($input['value'])
                ->required($contract->required)->rules([new NumericInputRule($contract)])->live(debounce: 250);
        }

        return Section::make($scenario->title)->description($scenario->description)
            ->extraAttributes(['class' => 'fff-playground-section'])
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema($fields),
                View::make('filament-flex-fields::partials.playground.intelligence.results')
                    ->viewData(static function (Get $get) use ($scenario): array {
                        $inputs = [];
                        foreach (array_keys($scenario->inputs) as $key) {
                            $inputs[$key] = $get($scenario->path($key));
                        }

                        return ['scenario' => $scenario, 'result' => $scenario->plan->evaluate($inputs)];
                    }),
            ]);
    }
}
