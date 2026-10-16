<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use Generator;
use InvalidArgumentException;

/** JS records have explicit `options` children, unlike keyed PHP option groups. */
final class TransportOptionTree
{
    /** Preserve group labels while capping actual options, rather than top-level groups.
     * @param  array<mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function take(array $options, int $limit): array
    {
        $remaining = max(0, $limit);
        $nodes = 100000;

        return $this->takeBranch($options, $remaining, $nodes, 0);
    }

    /**
     * @param  array<mixed>  $options
     * @return list<array<string, mixed>>
     */
    private function takeBranch(array $options, int &$remaining, int &$nodes, int $depth): array
    {
        if ($depth > 32) {
            throw new InvalidArgumentException('Select option groups exceed the maximum depth of 32.');
        }
        $result = [];
        foreach ($options as $option) {
            if ($remaining === 0) {
                break;
            }
            if (--$nodes < 0) {
                throw new InvalidArgumentException('Select option tree exceeds the node budget.');
            }
            if (! is_array($option)) {
                throw new InvalidArgumentException('Select transport options must be records.');
            }
            if (array_key_exists('options', $option)) {
                if (! is_array($option['options'])) {
                    throw new InvalidArgumentException('Select group options must be an array.');
                }
                $option['options'] = $this->takeBranch($option['options'], $remaining, $nodes, $depth + 1);
            } else {
                $remaining--;
            }
            $result[] = $option;
        }

        return $result;
    }

    /** @param array<mixed> $options
     * @return Generator<int, array<string, mixed>>
     */
    public function leaves(array $options, int $nodeBudget = 100000): Generator
    {
        yield from $this->walk($options, 0, $nodeBudget);
    }

    /** @param array<mixed> $options
     * @return Generator<int, array<string, mixed>>
     */
    private function walk(array $options, int $depth, int &$remaining): Generator
    {
        if ($depth > 32) {
            throw new InvalidArgumentException('Select option groups exceed the maximum depth of 32.');
        }
        foreach ($options as $option) {
            if (--$remaining < 0) {
                throw new InvalidArgumentException('Select option tree exceeds the node budget.');
            }
            if (! is_array($option)) {
                throw new InvalidArgumentException('Select transport options must be records.');
            }
            if (isset($option['options']) && is_array($option['options'])) {
                yield from $this->walk($option['options'], $depth + 1, $remaining);
            } else {
                yield $option;
            }
        }
    }
}
