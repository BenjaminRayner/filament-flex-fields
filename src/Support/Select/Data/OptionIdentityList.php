<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use InvalidArgumentException;

/** Ordered, bounded identities for suggestions; "1" and 1 address the same JS option. */
final class OptionIdentityList
{
    /** @return list<int|string> */
    public static function normalize(mixed $values): array
    {
        if (! is_array($values) || count($values) > 10000) {
            throw new InvalidArgumentException('Suggested option identities must be a bounded array.');
        }

        $result = [];
        $seen = [];
        $bytes = 0;
        foreach ($values as $value) {
            $identity = OptionValue::normalize($value);
            if ($identity === null || (is_string($identity) && ! mb_check_encoding($identity, 'UTF-8'))) {
                throw new InvalidArgumentException('Suggested option identities must be integers or UTF-8 strings.');
            }
            $key = (string) $identity;
            $bytes += strlen($key);
            if (strlen($key) > 4096 || $bytes > 1048576) {
                throw new InvalidArgumentException('Suggested option identities exceed their byte budget.');
            }
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $identity;
            }
        }

        return $result;
    }
}
