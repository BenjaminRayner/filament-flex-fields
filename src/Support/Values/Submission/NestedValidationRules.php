<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values\Submission;

use Bjanczak\FilamentFlexFields\Support\Values\FieldStoragePath;
use InvalidArgumentException;

/** Relative Laravel patterns, with bounded expansion before native validation. */
final class NestedValidationRules
{
    public const MAX_EXPANDED_PATHS = 4096;

    public const MAX_RULES = 65536;

    /** @var array<string, true> */
    private array $expandedPaths = [];

    private int $ruleCount = 0;

    /** @param array<string, list<string|object>> $rules */
    public static function assert(array $rules): void
    {
        if (count($rules) > 128) {
            throw new InvalidArgumentException('Nested validation requires a bounded rule map.');
        }
        foreach ($rules as $path => $list) {
            if (! is_array($list)) {
                throw new InvalidArgumentException('Nested rules require relative paths and rule lists.');
            }
            // Wildcards are entire segments, not arbitrary property expressions.
            FieldStoragePath::assert(implode('.', array_map(static fn (string $segment): string => $segment === '*' ? '0' : $segment, explode('.', (string) $path))));
            ValidationRuleList::assert($list);
        }
    }

    /**
     * @param  array<string, list<string|object>>  $target
     */
    public function append(FieldValueContract $contract, mixed $value, array &$target): void
    {
        $this->reserve($contract->path, count($contract->rules));
        $target[$contract->path] = $contract->rules;
        foreach ($contract->nestedRules as $pattern => $list) {
            $this->expand($contract->path, $value, explode('.', (string) $pattern), count($list));
            // Preserve native wildcard metadata: distinct, dependent rules and
            // Rule::forEach rely on Laravel seeing the original pattern.
            $target[$contract->path.'.'.$pattern] = $list;
        }
    }

    /**
     * @param  list<string>  $segments
     */
    private function expand(string $path, mixed $value, array $segments, int $rules): void
    {
        if ($segments === []) {
            $this->reserve($path, $rules);

            return;
        }
        $segment = array_shift($segments);
        if ($segment === '*') {
            if (! is_array($value)) {
                return;
            }
            foreach ($value as $key => $child) {
                // Laravel's dot parser cannot address literal dotted or wildcard keys.
                FieldStoragePath::assert((string) $key);
                if (str_contains((string) $key, '.')) {
                    throw new InvalidArgumentException('Nested wildcard values require literal key segments.');
                }
                $this->expand($path.'.'.$key, $child, $segments, $rules);
            }

            return;
        }
        $this->expand($path.'.'.$segment, is_array($value) ? ($value[$segment] ?? null) : null, $segments, $rules);
    }

    private function reserve(string $path, int $rules): void
    {
        FieldStoragePath::assert($path);
        if (isset($this->expandedPaths[$path])) {
            throw new InvalidArgumentException('Nested validation patterns overlap.');
        }
        $this->ruleCount += $rules;
        if (count($this->expandedPaths) >= self::MAX_EXPANDED_PATHS || $this->ruleCount > self::MAX_RULES) {
            throw new InvalidArgumentException('Submission exceeds its expanded validation budget.');
        }
        $this->expandedPaths[$path] = true;
    }
}
