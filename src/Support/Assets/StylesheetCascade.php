<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Support\Components\Compilation\CompiledComponentGraph;
use LogicException;
use WeakMap;

/** Structural CSS order; independent of registration, requests and activation. */
final readonly class StylesheetCascade
{
    /** @param array<string, int> $ranks */
    private function __construct(private array $ranks) {}

    public static function forGraph(CompiledComponentGraph $graph): self
    {
        /** @var WeakMap<CompiledComponentGraph, self>|null $cache */
        static $cache = null;
        $cache ??= new WeakMap;
        if (isset($cache[$graph])) {
            return $cache[$graph];
        }
        $definitions = $graph->definitions();
        $roots = array_keys($definitions);
        sort($roots, SORT_STRING);
        $done = [];
        $ranks = [];
        // The compiled graph has already rejected all cycles/unknown edges.
        // Iteration avoids recursive PHP stacks for deep external composition.
        foreach ($roots as $root) {
            $stack = [[$root, false]];
            while ($stack !== []) {
                [$id, $expanded] = array_pop($stack);
                if (isset($done[$id])) {
                    continue;
                }
                $definition = $definitions[$id];
                if (! $expanded) {
                    $stack[] = [$id, true];
                    $dependencies = array_values(array_unique([...$definition->stylesFrom, ...$definition->uses]));
                    rsort($dependencies, SORT_STRING);
                    foreach ($dependencies as $dependency) {
                        if (! isset($done[$dependency])) {
                            $stack[] = [$dependency, false];
                        }
                    }

                    continue;
                }
                foreach ($definition->styles as $stylesheet) {
                    $ranks[$stylesheet] = count($ranks);
                }
                $done[$id] = true;
            }
        }

        return $cache[$graph] = new self($ranks);
    }

    /** @return list<string> */
    public function all(): array
    {
        return array_keys($this->ranks);
    }

    /** @param list<string> $stylesheets
     * @return list<string>
     */
    public function project(array $stylesheets): array
    {
        $stylesheets = array_values(array_unique($stylesheets));
        foreach ($stylesheets as $stylesheet) {
            if (! isset($this->ranks[$stylesheet])) {
                throw new LogicException('Stylesheet has no compiled cascade owner: '.$stylesheet);
            }
        }
        usort($stylesheets, fn (string $a, string $b): int => $this->ranks[$a] <=> $this->ranks[$b]);

        return $stylesheets;
    }
}
