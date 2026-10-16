<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Intelligence;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use InvalidArgumentException;

final class GraphLaboratory
{
    public static function section(): Section
    {
        return Section::make('Dependencies & incremental updates')->description('See which outputs depend on each other and what actually changes when an input changes. Cycles are rejected before evaluation.')
            ->extraAttributes(['class' => 'fff-playground-section'])->schema([
                SelectField::make('intelligence__graph')->label('Dependency graph')->options([
                    'chain' => 'Valid chain', 'branches' => 'Independent branches', 'cycle' => 'Three-field cycle', 'self' => 'Self-reference',
                ])->required()->clearable(false)->in(['chain', 'branches', 'cycle', 'self'])->live(),
                View::make('filament-flex-fields::partials.playground.intelligence.graph')->viewData(static fn (Get $get): array => self::inspect($get('intelligence__graph'))),
            ]);
    }

    /** @return array{formulas: array<string, string>, order: list<string>, cycle: list<string>, values: array<string, float|int>, changed: array<string, float|int>, error: string|null} */
    public static function inspect(mixed $mode): array
    {
        $formulas = match ($mode) {
            'chain' => ['subtotal' => '{price}*{quantity}', 'tax' => 'round({subtotal}*pct({rate}),2)', 'total' => '{subtotal}+{tax}'],
            'branches' => ['total' => '{price}*{quantity}', 'progress' => 'if({goal}>0,{done}/{goal}*100,0)'],
            'cycle' => ['a' => '{b}+1', 'b' => '{c}+1', 'c' => '{a}+1'],
            'self' => ['total' => '{total}+1'],
            default => [],
        };
        $order = $cycle = $values = $changed = [];
        $error = null;
        try {
            if ($formulas === []) {
                throw new InvalidArgumentException('Choose a supported dependency graph.');
            }
            $cycle = FormulaEngine::detectCycle($formulas);
            $compiled = FormulaEngine::compileMap($formulas);
            $order = $compiled->computedKeys();
            $inputs = ['price' => 120, 'quantity' => 4, 'rate' => 23, 'done' => 18, 'goal' => 24];
            $values = $compiled->evaluate($inputs);
            $changed = $compiled->evaluate(['price' => 150] + $inputs + $values, ['price']);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }

        return compact('formulas', 'order', 'cycle', 'values', 'changed', 'error');
    }
}
