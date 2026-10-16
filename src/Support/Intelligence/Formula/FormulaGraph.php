<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Formula;

use InvalidArgumentException;

/** Bounded dependency graph. Diagnostic cycles include every member of a cycle. */
final class FormulaGraph
{
    /** @param array<string, string> $formulas
     * @return list<string>
     */
    public static function detectCycle(array $formulas): array
    {
        $dependencies = self::dependencies($formulas);
        $indices = $low = $stack = $onStack = $cyclic = [];
        foreach (array_keys($dependencies) as $node) {
            if (! isset($indices[$node])) {
                self::visit($node, $dependencies, $indices, $low, $stack, $onStack, $cyclic);
            }
        }
        $result = array_keys($cyclic);
        sort($result);

        return $result;
    }

    /** @param array<string, string> $formulas
     * @return list<string>
     */
    public static function topologicalOrder(array $formulas): array
    {
        $dependencies = self::dependencies($formulas);
        $dependents = $remaining = [];
        foreach ($dependencies as $key => $refs) {
            $remaining[$key] = count($refs);
            foreach ($refs as $ref) {
                $dependents[$ref][] = $key;
            }
        }
        $queue = array_keys(array_filter($remaining, static fn (int $count): bool => $count === 0));
        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            foreach ($dependents[$queue[$cursor]] ?? [] as $dependent) {
                if (--$remaining[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }
        if (count($queue) !== count($formulas)) {
            throw new InvalidArgumentException('Cannot evaluate formula map with dependency cycles.');
        }

        return $queue;
    }

    /** @param array<string, string> $formulas
     * @return array<string, list<string>>
     */
    private static function dependencies(array $formulas): array
    {
        if (count($formulas) > 512) {
            throw new InvalidArgumentException('Formula map exceeds its 512-field budget.');
        }
        $dependencies = [];
        foreach ($formulas as $key => $expression) {
            if (! is_string($key) || strlen($key) > 128 || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $key) || ! is_string($expression)) {
                throw new InvalidArgumentException('Formula maps require valid field names and string expressions.');
            }
            FormulaProgram::compile($expression);
            $dependencies[$key] = array_values(array_filter(FormulaLexer::fieldReferences($expression),
                static fn (string $reference): bool => array_key_exists($reference, $formulas)));
        }

        return $dependencies;
    }

    /** @param array<string, list<string>> $dependencies
     * @param  array<string, int>  $indices
     * @param  array<string, int>  $low
     * @param  list<string>  $stack
     * @param  array<string, true>  $onStack
     * @param  array<string, true>  $cyclic
     */
    private static function visit(string $node, array $dependencies, array &$indices, array &$low, array &$stack, array &$onStack, array &$cyclic): void
    {
        $indices[$node] = $low[$node] = count($indices);
        $stack[] = $node;
        $onStack[$node] = true;
        foreach ($dependencies[$node] as $dependency) {
            if (! isset($indices[$dependency])) {
                self::visit($dependency, $dependencies, $indices, $low, $stack, $onStack, $cyclic);
                $low[$node] = min($low[$node], $low[$dependency]);
            } elseif (isset($onStack[$dependency])) {
                $low[$node] = min($low[$node], $indices[$dependency]);
            }
        }
        if ($low[$node] !== $indices[$node]) {
            return;
        }
        $members = [];
        do {
            $member = array_pop($stack);
            unset($onStack[$member]);
            $members[] = $member;
        } while ($member !== $node);
        if (count($members) > 1 || in_array($node, $dependencies[$node], true)) {
            foreach ($members as $member) {
                $cyclic[$member] = true;
            }
        }
    }
}
