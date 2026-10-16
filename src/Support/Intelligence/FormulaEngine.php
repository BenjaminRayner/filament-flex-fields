<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationPlan;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\CompiledFormulaMap;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\FormulaCatalog;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\FormulaEvaluator;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\FormulaGraph;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\FormulaLexer;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\FormulaProgram;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Values\MoneyValue;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Values\NumericValue;
use InvalidArgumentException;

/** Public formula API. Syntax, values, graph planning and validation have separate owners. */
final class FormulaEngine
{
    /** @param array<string, mixed> $values */
    public static function evaluate(string $expression, array $values, bool $nullAsZero = false): float|int
    {
        return FormulaEvaluator::evaluate(FormulaProgram::compile($expression), static function (string $key) use ($values, $nullAsZero): float {
            if (! array_key_exists($key, $values) && ! $nullAsZero) {
                throw new InvalidArgumentException("Missing value for formula field [{$key}].");
            }

            return NumericValue::read($values[$key] ?? null, $nullAsZero);
        });
    }

    /** @param array<string, mixed> $values */
    public static function tryEvaluate(string $expression, array $values, bool $nullAsZero = false): float|int|null
    {
        try {
            return self::evaluate($expression, $values, $nullAsZero);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return list<string> */
    public static function validate(string $expression): array
    {
        try {
            FormulaProgram::compile($expression);

            return [];
        } catch (InvalidArgumentException $exception) {
            return [$exception->getMessage()];
        }
    }

    /** @param array<string, string> $formulas
     * @param  array<string, mixed>  $values
     * @return array<string, float|int>
     */
    public static function evaluateMap(array $formulas, array $values, bool $nullAsZero = false): array
    {
        return self::compileMap($formulas)->evaluate($values, null, $nullAsZero);
    }

    /** @param array<string, string> $formulas */
    public static function compileMap(array $formulas): CompiledFormulaMap
    {
        return new CompiledFormulaMap($formulas);
    }

    /** @param array<string, string> $formulas
     * @param  array<string, NumericInput>  $inputs
     * @param  list<string>|null  $allowedFunctions
     */
    public static function plan(array $formulas, array $inputs, ?array $allowedFunctions = null): CalculationPlan
    {
        return new CalculationPlan($formulas, $inputs, $allowedFunctions);
    }

    /** @param array<string, mixed> $values
     * @return array{expression: string, references: list<string>, substituted: string, result: float|int, functions: list<string>}
     */
    public static function explain(string $expression, array $values, bool $nullAsZero = false): array
    {
        $result = self::evaluate($expression, $values, $nullAsZero);
        try {
            $substituted = preg_replace_callback(FormulaCatalog::FIELD_REF_PATTERN, static function (array $match) use ($values, $nullAsZero): string {
                $number = NumericValue::read($values[$match[1]] ?? null, $nullAsZero);

                return $number < 0 ? '('.$number.')' : (string) $number;
            }, $expression) ?? $expression;
        } catch (InvalidArgumentException) {
            $substituted = $expression;
        }

        return [
            'expression' => $expression,
            'references' => self::fieldReferences($expression),
            'substituted' => $substituted,
            'result' => $result,
            'functions' => self::functionsUsed($expression),
        ];
    }

    public static function toNumber(mixed $value, bool $nullAsZero = false): float
    {
        return NumericValue::read($value, $nullAsZero);
    }

    public static function moneyMajor(mixed $value, int $fractionDigits = 2, bool $nullAsZero = false): float
    {
        return MoneyValue::major($value, $fractionDigits, $nullAsZero);
    }

    public static function moneyMinor(float|int $major, int $fractionDigits = 2): int
    {
        return MoneyValue::minor($major, $fractionDigits);
    }

    /** @return list<string> */
    public static function allowedFunctions(): array
    {
        return FormulaCatalog::ALLOWED_FUNCTIONS;
    }

    public static function formulasEnabled(): bool
    {
        return true;
    }

    /** @param array<string, string> $formulas
     * @return list<string>
     */
    public static function detectCycle(array $formulas): array
    {
        return FormulaGraph::detectCycle($formulas);
    }

    /** @return list<string> */
    public static function fieldReferences(string $expression): array
    {
        return FormulaLexer::fieldReferences($expression);
    }

    /** @return list<string> */
    public static function functionsUsed(string $expression): array
    {
        return FormulaLexer::functionsUsed($expression);
    }
}
