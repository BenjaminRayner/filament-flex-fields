<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/** Compact validated DAG with bounded, value-free projection memoization. */
final class ComponentProjections
{
    /** @var array<string, ComponentDefinition> */
    private readonly array $definitions;

    /** @var array<string, array<string, list<string>>> */
    private array $cache = [];

    private int $members = 0;

    /** @param array<string, ComponentDefinition> $definitions */
    public function __construct(array $definitions)
    {
        $this->definitions = ArraySnapshot::copy($definitions);
    }

    /** @return list<string> */
    public function stylesFor(string $id): array
    {
        return $this->project($id, true);
    }

    /** @return list<string> */
    public function runtimeComponents(string $id): array
    {
        return $this->project($id, false);
    }

    /** @return list<string> */
    private function project(string $id, bool $styles): array
    {
        if (! isset($this->definitions[$id])) {
            throw new InvalidArgumentException('Unknown Flex component: '.$id);
        }
        $kind = $styles ? 'styles' : 'runtime';
        if (isset($this->cache[$id][$kind])) {
            $entry = $this->cache[$id];
            unset($this->cache[$id]);
            $this->cache[$id] = $entry;

            return $entry[$kind];
        }
        // Iterative postorder visits each reachable node once, preserving the
        // declared dependency/style order without materializing every closure.
        $visited = [];
        $result = [];
        $stack = [[$id, false]];
        while ($stack !== []) {
            [$current, $finished] = array_pop($stack);
            $definition = $this->definitions[$current];
            if ($finished) {
                foreach ($styles ? $definition->styles : [$current] as $member) {
                    $result[$member] = true;
                }

                continue;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $stack[] = [$current, true];
            $dependencies = $styles ? [...$definition->stylesFrom, ...$definition->uses] : $definition->uses;
            for ($index = count($dependencies) - 1; $index >= 0; $index--) {
                $stack[] = [$dependencies[$index], false];
            }
        }
        $projection = array_keys($result);
        // Both projections share one component LRU entry, avoiding mutual eviction
        // while keeping the total retained member budget across both kinds.
        $count = count($projection);
        if ($count <= 65536) {
            while ($this->cache !== [] && ((! isset($this->cache[$id]) && count($this->cache) >= 256) || $this->members + $count > 65536)) {
                $oldest = array_key_first($this->cache);
                foreach ($this->cache[$oldest] as $retained) {
                    $this->members -= count($retained);
                }
                unset($this->cache[$oldest]);
            }
            $entry = $this->cache[$id] ?? [];
            unset($this->cache[$id]);
            $entry[$kind] = $projection;
            $this->cache[$id] = $entry;
            $this->members += $count;
        }

        return $projection;
    }
}
