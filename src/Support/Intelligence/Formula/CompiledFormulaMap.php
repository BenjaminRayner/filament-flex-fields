<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Formula;

use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use InvalidArgumentException;

/** Immutable syntax and dependency plan; values remain local to each evaluation. */
final class CompiledFormulaMap
{
    /** @var list<string> */
    private readonly array $order;

    /** @var array<string, list<string>> */
    private array $dependents = [];

    /** @var array<string, array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}> */
    private array $programs = [];

    /** @var array<string, bool> */
    private array $valueKeys = [];

    /** @param array<string, string> $formulas */
    public function __construct(array $formulas)
    {
        if (count($formulas) > 512) {
            throw new InvalidArgumentException('Formula map exceeds its 512-field budget.');
        }

        foreach ($formulas as $key => $expression) {
            if (! is_string($key) || strlen($key) > 128 || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $key) || ! is_string($expression)) {
                throw new InvalidArgumentException('Formula maps require valid field names and string expressions.');
            }
            $this->programs[$key] = FormulaProgram::compile($expression);
            $this->valueKeys[$key] = true;

            foreach (FormulaLexer::fieldReferences($expression) as $reference) {
                $this->dependents[$reference][] = $key;
                $this->valueKeys[$reference] = true;
            }
        }
        $this->order = FormulaGraph::topologicalOrder($formulas);
    }

    /** @return list<string> All input and computed paths read by this plan. */
    public function valueKeys(): array
    {
        return array_keys($this->valueKeys);
    }

    /** @return list<string> */
    public function computedKeys(): array
    {
        return $this->order;
    }

    /**
     * Incremental evaluation requires previous computed values and changed input paths.
     * Returns only recomputed outputs; no caller data enters a persistent cache.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>|null  $changed
     * @return array<string, float|int>
     */
    public function evaluate(array $values, ?array $changed = null, bool $nullAsZero = false): array
    {
        if ($changed !== null) {
            foreach ($changed as $key) {
                if (! is_string($key) || ! isset($this->valueKeys[$key])) {
                    throw new InvalidArgumentException('Changed paths must belong to the compiled calculation.');
                }
            }
            foreach ($this->order as $key) {
                if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                    $changed = null;
                    break;
                }
            }
        }
        $affected = $changed === null ? array_fill_keys($this->order, true) : [];
        $queue = $changed ?? [];
        foreach ($queue as $key) {
            if (isset($this->programs[$key])) {
                $affected[$key] = true;
            }
        }

        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            foreach ($this->dependents[$queue[$cursor]] ?? [] as $dependent) {
                if (! isset($affected[$dependent])) {
                    $affected[$dependent] = true;
                    $queue[] = $dependent;
                }
            }
        }

        $result = [];

        foreach ($this->order as $key) {
            if (! isset($affected[$key])) {
                continue;
            }

            $value = FormulaEvaluator::evaluate($this->programs[$key], static function (string $reference) use (&$values, $nullAsZero): float {
                if (! array_key_exists($reference, $values) && ! $nullAsZero) {
                    throw new InvalidArgumentException("Missing value for formula field [{$reference}].");
                }

                return FormulaEngine::toNumber($values[$reference] ?? null, $nullAsZero);
            });
            $values[$key] = $value;
            $result[$key] = $value;
        }

        return $result;
    }
}
