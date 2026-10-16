<?php

declare(strict_types=1);

/**
 * @author Bartłomiej Janczak <barek122@gmail.com>
 * @copyright Copyright (c) 2026 Bartłomiej Janczak. All rights reserved.
 * @license Proprietary
 */

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Formula;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Values\NumericValue;
use Closure;
use InvalidArgumentException;

/**
 * AST walker with short-circuit evaluation for control-flow helpers.
 *
 * @internal
 */
final class FormulaEvaluator
{
    /**
     * @param  array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}  $ast
     */
    public static function evaluate(array $ast, ?Closure $resolveReference = null): float|int
    {
        return self::normalize((new self($resolveReference))->visit($ast));
    }

    /** @param array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed} $ast */
    public static function evaluateAst(array $ast, ?Closure $resolveReference = null): float
    {
        return (new self($resolveReference))->visit($ast);
    }

    private function __construct(private readonly ?Closure $resolveReference) {}

    /** @param array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed} $ast */
    private function visit(array $ast): float
    {
        return NumericValue::finite(match ($ast[0]) {
            'lit' => (float) $ast[1],
            'ref' => $this->reference($ast[1] ?? null),
            'un' => $this->evaluateUnary((string) $ast[1], $ast[2]),
            'bin' => $this->evaluateBinary((string) $ast[1], $ast[2], $ast[3]),
            'call' => $this->evaluateCall((string) $ast[1], $ast[2]),
            default => throw new InvalidArgumentException('Unexpected formula AST node.'),
        });
    }

    private function reference(mixed $key): float
    {
        if (! is_string($key) || $key === '' || $this->resolveReference === null) {
            throw new InvalidArgumentException('Invalid formula reference or missing value resolver.');
        }

        return (float) ($this->resolveReference)($key);
    }

    /**
     * @param  array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}  $child
     */
    private function evaluateUnary(string $operator, array $child): float
    {
        $value = $this->visit($child);

        return match ($operator) {
            '-' => -$value,
            default => throw new InvalidArgumentException('Unexpected unary operator.'),
        };
    }

    /**
     * @param  array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}  $left
     * @param  array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}  $right
     */
    private function evaluateBinary(string $operator, array $left, array $right): float
    {
        $leftValue = $this->visit($left);
        $rightValue = $this->visit($right);

        return match ($operator) {
            '+' => $leftValue + $rightValue,
            '-' => $leftValue - $rightValue,
            '*' => $leftValue * $rightValue,
            '/' => self::safeDivide($leftValue, $rightValue),
            '%' => self::safeModulo($leftValue, $rightValue),
            '>' => $leftValue > $rightValue ? 1.0 : 0.0,
            '<' => $leftValue < $rightValue ? 1.0 : 0.0,
            '>=' => $leftValue >= $rightValue ? 1.0 : 0.0,
            '<=' => $leftValue <= $rightValue ? 1.0 : 0.0,
            '==' => $leftValue == $rightValue ? 1.0 : 0.0,
            '!=' => $leftValue != $rightValue ? 1.0 : 0.0,
            default => throw new InvalidArgumentException('Unexpected binary operator.'),
        };
    }

    /**
     * @param  list<array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}>  $arguments
     */
    private function evaluateCall(string $name, array $arguments): float
    {
        if (in_array($name, FormulaCatalog::SHORT_CIRCUIT_FUNCTIONS, true)) {
            return $this->evaluateShortCircuit($name, $arguments);
        }

        $values = array_map(
            fn (array $argument): float => $this->visit($argument),
            $arguments,
        );

        return FormulaFunctions::call($name, $values);
    }

    /**
     * @param  list<array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}>  $arguments
     */
    private function evaluateShortCircuit(string $name, array $arguments): float
    {
        return match ($name) {
            'if' => $this->evaluateIf($arguments),
            'and' => $this->evaluateAnd($arguments),
            'or' => $this->evaluateOr($arguments),
            'coalesce', 'nz' => $this->evaluateCoalesce($arguments),
            default => throw new InvalidArgumentException("Unknown short-circuit formula function [{$name}]."),
        };
    }

    /**
     * @param  list<array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}>  $arguments
     */
    private function evaluateIf(array $arguments): float
    {
        if (count($arguments) !== 3) {
            throw new InvalidArgumentException('Function [if] expects exactly 3 arguments: condition, whenTrue, whenFalse.');
        }

        $condition = $this->visit($arguments[0]);

        return $condition != 0.0
            ? $this->visit($arguments[1])
            : $this->visit($arguments[2]);
    }

    /**
     * @param  list<array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}>  $arguments
     */
    private function evaluateAnd(array $arguments): float
    {
        if (count($arguments) < 2) {
            throw new InvalidArgumentException('Function [and] expects at least 2 arguments.');
        }

        foreach ($arguments as $argument) {
            if ($this->visit($argument) == 0.0) {
                return 0.0;
            }
        }

        return 1.0;
    }

    /**
     * @param  list<array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}>  $arguments
     */
    private function evaluateOr(array $arguments): float
    {
        if (count($arguments) < 2) {
            throw new InvalidArgumentException('Function [or] expects at least 2 arguments.');
        }

        foreach ($arguments as $argument) {
            if ($this->visit($argument) != 0.0) {
                return 1.0;
            }
        }

        return 0.0;
    }

    /**
     * @param  list<array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}>  $arguments
     */
    private function evaluateCoalesce(array $arguments): float
    {
        if ($arguments === []) {
            throw new InvalidArgumentException('Function [coalesce] expects at least 1 argument.');
        }

        $last = 0.0;

        foreach ($arguments as $argument) {
            $last = $this->visit($argument);

            if ($last != 0.0) {
                return $last;
            }
        }

        return $last;
    }

    private static function safeDivide(float $left, float $right): float
    {
        if ($right == 0.0) {
            throw new InvalidArgumentException('Division by zero in formula expression.');
        }

        return $left / $right;
    }

    private static function safeModulo(float $left, float $right): float
    {
        if ($right == 0.0) {
            throw new InvalidArgumentException('Modulo by zero in formula expression.');
        }

        return fmod($left, $right);
    }

    private static function normalize(float $value): float|int
    {
        return NumericValue::normalize($value);
    }
}
