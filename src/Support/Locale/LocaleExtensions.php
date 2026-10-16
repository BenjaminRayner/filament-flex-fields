<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Locale;

use InvalidArgumentException;

/** RFC 5646 extension syntax; not an IANA/Unicode registry or alias resolver. */
final class LocaleExtensions
{
    /** @param list<string> $parts
     * @return list<string>
     */
    public static function normalize(array $parts): array
    {
        $result = [];
        $seen = [];
        $position = 0;
        while (isset($parts[$position])) {
            $singleton = strtolower($parts[$position++]);
            if (preg_match('/^[a-z0-9]$/D', $singleton) !== 1 || isset($seen[$singleton])) {
                throw new InvalidArgumentException('Invalid or duplicate FLEX locale extension.');
            }
            $seen[$singleton] = true;
            $result[] = $singleton;
            $start = $position;
            $pattern = $singleton === 'x' ? '/^[a-z0-9]{1,8}$/D' : '/^[a-z0-9]{2,8}$/D';
            while (isset($parts[$position]) && ($singleton === 'x' || strlen($parts[$position]) !== 1)) {
                $part = strtolower($parts[$position++]);
                if (preg_match($pattern, $part) !== 1) {
                    throw new InvalidArgumentException('Invalid FLEX locale extension subtag.');
                }
                $result[] = $part;
            }
            if ($position === $start) {
                throw new InvalidArgumentException('FLEX locale extension needs a value.');
            }
        }

        return $result;
    }
}
