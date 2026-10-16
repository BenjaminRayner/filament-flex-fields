<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Matrix;

use Bjanczak\FilamentFlexFields\Support\ChoiceLists\OptionCatalog;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\Validation\SelectionLimits;
use InvalidArgumentException;

final class DimensionCatalog
{
    /**
     * @param array<string|int,string|array<string,mixed>> $input
     * @param array<string|int> $disabled
     * @param array<string|int> $required
     * @return array<string|int,array{label:string,description:?string,disabled:bool,required:bool,min_selections:?int,max_selections:?int}> */
    public static function rows(array $input, array $disabled = [], array $required = [], bool $allRequired = false): array
    {
        $requiredKeys = array_fill_keys(array_map(strval(...), $required), true);
        $result = [];
        foreach (OptionCatalog::normalize($input, disabled: $disabled) as $key => $base) {
            $raw = is_array($input[$key]) ? $input[$key] : [];
            $min = self::limit($raw['min_selections'] ?? $raw['min'] ?? null);
            $max = self::limit($raw['max_selections'] ?? $raw['max'] ?? null);
            new SelectionLimits($min, $max, null);
            $result[$key] = ['label' => $base['label'], 'description' => $base['description'], 'disabled' => $base['disabled'],
                'required' => array_key_exists('required', $raw) ? (bool) $raw['required'] : (isset($requiredKeys[$key]) || ($allRequired && $required === [])),
                'min_selections' => $min, 'max_selections' => $max,
            ];
        }

        return $result;
    }

    /**
     * @param array<string|int,string|array<string,mixed>> $input
     * @return array<string|int,array<string,mixed>> */
    public static function tableRows(array $input): array
    {
        $result = [];
        foreach (OptionCatalog::normalize($input) as $key => $base) {
            if ((string) $key === '' || str_contains((string) $key, '.')) {
                throw new InvalidArgumentException('Matrix table row keys must be nonempty and cannot contain dots.');
            }
            $result[$key] = [...(is_array($input[$key]) ? $input[$key] : []), 'label' => $base['label'], 'description' => $base['description'], 'disabled' => $base['disabled']];
        }

        return $result;
    }

    private static function limit(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException('Matrix selection limits must be nonnegative integers.');
        }
        $limit = filter_var($value, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 0) {
            throw new InvalidArgumentException('Invalid matrix selection limit.');
        }

        return $limit;
    }
}
