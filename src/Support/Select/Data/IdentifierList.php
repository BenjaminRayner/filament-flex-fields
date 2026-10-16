<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/** Catalog restrictions compile atomically; invalid input cannot silently widen a policy. */
final class IdentifierList
{
    /** @return list<string> */
    public static function normalize(mixed $values, bool $uppercase = false): array
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }
        if (! is_array($values) || count($values) > 10000) {
            throw new InvalidArgumentException('Picker identifiers must be a bounded array or Arrayable.');
        }
        $result = [];
        $bytes = 0;
        foreach ($values as $value) {
            if (! is_string($value) || strlen($value) > 4096 || ! mb_check_encoding($value, 'UTF-8')) {
                throw new InvalidArgumentException('Picker identifiers must be bounded UTF-8 strings.');
            }
            $value = trim($value);
            if ($value === '') {
                throw new InvalidArgumentException('Picker identifiers cannot be empty.');
            }
            $bytes += strlen($value);
            if ($bytes > 1048576) {
                throw new InvalidArgumentException('Picker identifier list exceeds its byte budget.');
            }
            $value = $uppercase ? strtoupper($value) : $value;
            $result[$value] = $value;
        }

        return array_values($result);
    }
}
