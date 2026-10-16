<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use Closure;
use Generator;
use InvalidArgumentException;

/** Bounded leaf traversal shared by validation and selected-label presentation. */
final class OptionTree
{
    /**
     * Shape-preserving transformation with one budget shared by every branch.
     *
     * @param  array<string|int, mixed>  $options
     * @param  Closure(array<mixed>): bool  $isGroup
     * @param  Closure(string|int, mixed): ?array<string, mixed>  $leaf
     * @return list<array<string, mixed>>
     */
    public function map(array $options, Closure $isGroup, Closure $leaf, int $nodeBudget = 100000, int $depth = 0): array
    {
        return $this->mapBranch($options, $isGroup, $leaf, $depth, $nodeBudget);
    }

    /**
     * @param  array<string|int, mixed>  $options
     * @param  Closure(array<mixed>): bool  $isGroup
     * @param  Closure(string|int, mixed): ?array<string, mixed>  $leaf
     * @return list<array<string, mixed>>
     */
    private function mapBranch(array $options, Closure $isGroup, Closure $leaf, int $depth, int &$remaining): array
    {
        if ($depth > 32) {
            throw new InvalidArgumentException('Select option groups exceed the maximum depth of 32.');
        }
        $mapped = [];
        foreach ($options as $value => $label) {
            if (--$remaining < 0) {
                throw new InvalidArgumentException('Select option tree exceeds the node budget.');
            }
            if (is_array($label) && $isGroup($label)) {
                $mapped[] = ['label' => (string) $value, 'options' => $this->mapBranch($label, $isGroup, $leaf, $depth + 1, $remaining)];
            } else {
                $option = $leaf($value, $label);
                if ($option !== null) {
                    $mapped[] = $option;
                }
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string|int, mixed>  $options
     * @param  Closure(array<mixed>): bool  $isGroup
     * @return Generator<int, array{0: string|int, 1: mixed}>
     */
    public function leaves(array $options, Closure $isGroup, int $depth = 0, int $nodeBudget = 100000): Generator
    {
        yield from $this->walkLeaves($options, $isGroup, $depth, $nodeBudget);
    }

    /**
     * @param  array<string|int, mixed>  $options
     * @param  Closure(array<mixed>): bool  $isGroup
     * @return Generator<int, array{0: string|int, 1: mixed}>
     */
    private function walkLeaves(array $options, Closure $isGroup, int $depth, int &$remaining): Generator
    {
        if ($depth > 32) {
            throw new InvalidArgumentException('Select option groups exceed the maximum depth of 32.');
        }

        foreach ($options as $value => $label) {
            if (--$remaining < 0) {
                throw new InvalidArgumentException('Select option tree exceeds the node budget.');
            }
            if (is_array($label) && $isGroup($label)) {
                yield from $this->walkLeaves($label, $isGroup, $depth + 1, $remaining);
            } else {
                yield [$value, $label];
            }
        }
    }
}
