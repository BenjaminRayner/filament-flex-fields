<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use Closure;

/** One bounded traversal, then constant-time lookups for an entire selection. */
final class OptionLabelIndex
{
    /** @var array<string|int, string|array<string, mixed>> */
    private array $labels = [];

    /**
     * @param  array<string|int, mixed>  $options
     * @param  Closure(array<mixed>): bool  $isGroup
     */
    public function __construct(array $options, Closure $isGroup)
    {
        foreach ((new OptionTree)->leaves($options, $isGroup) as [$value, $label]) {
            // Match the former depth-first findOptionLabel: first duplicate wins.
            if (! array_key_exists($value, $this->labels) && (is_string($label) || is_array($label))) {
                $this->labels[$value] = $label;
            }
        }
    }

    /** @return string|array<string, mixed>|null */
    public function find(mixed $value): string|array|null
    {
        $key = OptionValue::normalize($value);

        return $key === null ? null : ($this->labels[$key] ?? null);
    }
}
