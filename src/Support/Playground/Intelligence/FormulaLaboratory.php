<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Intelligence;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationPlan;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationResult;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use InvalidArgumentException;

final class FormulaLaboratory
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return ['intelligence__expression' => 'round({a}*{b}*(1-pct({c})),2)', 'intelligence__a' => 120,
            'intelligence__b' => 4, 'intelligence__c' => 15, 'intelligence__policy' => 'standard'];
    }

    public static function section(): Section
    {
        return Section::make('Formula workspace')->description('Write a formula with a, b and c. Inspect its references, allowed functions and validation without saving anything.')
            ->extraAttributes(['class' => 'fff-playground-section'])->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    FlexTextareaField::make('intelligence__expression')->label('Expression')->rows(2)->maxLength(8192)->live(debounce: 250)
                        ->helperText('Try if({b}>0,{a}/{b},0). PHP, JavaScript and undeclared references are rejected.'),
                    SelectField::make('intelligence__policy')->label('Function policy')->options([
                        'standard' => 'All supported functions', 'restricted' => 'Restricted: round, min, max, if',
                    ])->required()->clearable(false)->in(['standard', 'restricted'])->live(),
                ]),
                Grid::make(['default' => 1, 'sm' => 3])->schema(array_map(
                    static fn (string $key): NumberStepper => NumberStepper::make('intelligence__'.$key)->label(strtoupper($key))->minValue(-1000000)->maxValue(1000000)->live(debounce: 250),
                    ['a', 'b', 'c'],
                )),
                View::make('filament-flex-fields::partials.playground.intelligence.formula')->viewData(static fn (Get $get): array => self::inspect(
                    $get('intelligence__expression'), ['a' => $get('intelligence__a'), 'b' => $get('intelligence__b'), 'c' => $get('intelligence__c')], $get('intelligence__policy'),
                )),
            ]);
    }

    /** @param array<string, mixed> $values
     * @return array{expression: string, references: list<string>, functions: list<string>, result: CalculationResult}
     */
    public static function inspect(mixed $expression, array $values, mixed $policy): array
    {
        $text = is_string($expression) ? $expression : '';
        try {
            if (! in_array($policy, ['standard', 'restricted'], true)) {
                throw new InvalidArgumentException('Choose a supported function policy.');
            }
            $plan = new CalculationPlan(['result' => $text], [
                'a' => new NumericInput(-1000000, 1000000), 'b' => new NumericInput(-1000000, 1000000), 'c' => new NumericInput(-1000000, 1000000),
            ], $policy === 'restricted' ? ['round', 'min', 'max', 'if'] : null);
            $result = $plan->evaluate($values);
        } catch (InvalidArgumentException $exception) {
            $result = new CalculationResult([], ['expression' => $exception->getMessage()]);
        }

        return ['expression' => $text, 'references' => FormulaEngine::fieldReferences($text), 'functions' => FormulaEngine::functionsUsed($text), 'result' => $result];
    }
}
