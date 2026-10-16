<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

/** One precedence contract for admission, dependency graphs and field construction. */
final class FieldFormula
{
    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $configuration
     */
    public static function resolve(array $field, array $configuration = []): ?string
    {
        $configuration += is_array($field['config'] ?? null) ? $field['config'] : [];
        foreach ([$field['formula'] ?? null, $field['calculated'] ?? null,
            $configuration['formula'] ?? null, $configuration['calculated'] ?? null] as $expression) {
            if (is_string($expression) && trim($expression) !== '') {
                return trim($expression);
            }
        }

        return null;
    }
}
