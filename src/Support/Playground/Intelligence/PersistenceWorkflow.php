<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Intelligence;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationPlan;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\CalculatedFields;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;
use Filament\Schemas\Components\Section;

final class PersistenceWorkflow
{
    public static function section(): Section
    {
        $plan = new CalculationPlan(['total' => 'round({price}*{quantity},2)'], [
            'price' => new NumericInput(0, 1000000), 'quantity' => new NumericInput(1, 1000, integer: true),
        ]);
        $binding = new CalculatedFields($plan, prefix: 'intelligence__save__');

        return Section::make('Calculated fields in a real form')->description('These are actual bound FLEX fields. The disabled total updates live and is recalculated again when the form is dehydrated for saving. Invalid inputs cannot produce a saved total.')
            ->extraAttributes(['class' => 'fff-playground-section'])->columns(['default' => 1, 'sm' => 3])
            ->schema($binding->bind([
                'price' => NumberStepper::make('intelligence__save__price')->label('Unit price')->step(0.5)->minValue(0)->maxValue(1000000)->default(120),
                'quantity' => NumberStepper::make('intelligence__save__quantity')->label('Quantity')->minValue(1)->maxValue(1000)->default(4),
                'total' => FlexTextInput::make('intelligence__save__total')->label('Server-calculated total')->helperText('round({price}*{quantity},2)'),
            ]));
    }
}
