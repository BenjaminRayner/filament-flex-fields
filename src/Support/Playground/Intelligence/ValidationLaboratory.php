<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Intelligence;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationPlan;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationResult;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;

final class ValidationLaboratory
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return ['valid' => 'Valid: 20', 'missing' => 'Missing required input', 'text' => 'Text instead of a number', 'fraction' => 'Fraction instead of a whole number',
            'negative' => 'Below the minimum', 'limit' => 'Above the maximum', 'array' => 'Nested payload instead of a number', 'infinite' => 'Infinite number',
            'forged' => 'Forged computed output', 'overflow' => 'Intermediate arithmetic overflow'];
    }

    public static function section(): Section
    {
        return Section::make('Validation & tamper protection')->description('The input contract accepts integers from 0 to 100. Invalid payloads expose no calculated outputs. A forged result is recalculated from the inputs.')
            ->extraAttributes(['class' => 'fff-playground-section'])->schema([
                SelectField::make('intelligence__validation')->label('Payload to inspect')->options(self::options())->required()->clearable(false)->in(array_keys(self::options()))->live(),
                View::make('filament-flex-fields::partials.playground.intelligence.validation')->viewData(static fn (Get $get): array => self::inspect($get('intelligence__validation'))),
            ]);
    }

    /** @return array{payload: string, result: CalculationResult} */
    public static function inspect(mixed $mode): array
    {
        $value = match ($mode) {
            'valid', 'forged', 'overflow' => 20,
            'missing' => null,
            'text' => 'not a number',
            'fraction' => 2.5,
            'negative' => -1,
            'limit' => 101,
            'array' => ['amount' => 20, 'injected' => true],
            'infinite' => INF,
            default => null,
        };
        $payload = ['input' => $value];
        if ($mode === 'forged') {
            $payload['result'] = 999999;
        }
        $formula = $mode === 'overflow' ? str_repeat('pow(999999999999,12)*', 3).'pow(999999999999,12)' : '{input}*2';
        $plan = new CalculationPlan(['result' => $formula], ['input' => new NumericInput(0, 100, integer: true)]);
        $serialized = $mode === 'infinite' ? '{"input": Infinity}' : (json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');

        return ['payload' => $serialized, 'result' => $plan->evaluate($payload)];
    }
}
