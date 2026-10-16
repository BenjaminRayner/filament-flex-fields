<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Validation;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionTree;
use Closure;

final class StaticOptionSelection
{
    /** Validate submitted identities before hydration can normalize their shape. */
    public function acceptsShape(mixed $value, bool $multiple): bool
    {
        if (! $multiple) {
            return is_string($value) || is_int($value) || is_bool($value) || $value instanceof BackedEnum;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        $seen = [];
        foreach ($value as $candidate) {
            if ($candidate instanceof BackedEnum) {
                $candidate = $candidate->value;
            }
            if ((! is_string($candidate) && ! is_int($candidate)) || isset($seen[(string) $candidate])) {
                return false;
            }
            $seen[(string) $candidate] = true;
        }

        return true;
    }

    /**
     * Strict submitted list for fields whose UI emits unique string/int identities.
     * Hydration may be forgiving; validation must inspect the original payload.
     *
     * @param  list<string>  $enabledKeys
     */
    public function acceptsList(array $enabledKeys, mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > count($enabledKeys)) {
            return false;
        }
        $allowed = array_fill_keys($enabledKeys, true);
        $seen = [];
        foreach ($value as $candidate) {
            if ((! is_string($candidate) && ! is_int($candidate)) || ! isset($allowed[(string) $candidate]) || isset($seen[(string) $candidate])) {
                return false;
            }
            $seen[(string) $candidate] = true;
        }

        return true;
    }

    /**
     * @param  list<string>  $enabledKeys
     *
     * Membership is indexed once for an entire submitted selection. Structured
     * candidates fail validation before conversion, without PHP cast warnings.
     */
    public function accepts(array $enabledKeys, mixed $value, bool $multiple): bool
    {
        if (! $this->acceptsShape($value, $multiple)) {
            return false;
        }

        $allowed = array_fill_keys($enabledKeys, true);
        $candidates = $multiple ? $value : [$value];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof BackedEnum) {
                $candidate = $candidate->value;
            }

            if (is_bool($candidate)) {
                $candidate = (int) $candidate;
            }

            if ((! is_string($candidate) && ! is_int($candidate)) || ! isset($allowed[(string) $candidate])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string|int, mixed>  $options
     * @param  Closure(string|int, mixed): array<string, mixed>  $normalize
     * @param  Closure(array<mixed>): bool  $isGroup
     * @return list<string>
     */
    public function enabledKeys(array $options, Closure $normalize, Closure $isGroup): array
    {
        $keys = [];
        foreach ((new OptionTree)->leaves($options, $isGroup) as [$value, $label]) {
            $option = $normalize($value, $label);
            if (! ($option['disabled'] ?? false)) {
                $keys[] = (string) $value;
            }
        }

        return array_values(array_unique($keys));
    }
}
