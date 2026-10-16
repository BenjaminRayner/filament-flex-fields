<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\UrlMeta;

final class MetadataText
{
    public static function clean(mixed $value, int $length): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim(preg_replace('/[\s\p{Z}\x00-\x1f\x7f]+/u', ' ', strip_tags($value)) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    /** @param list<mixed> $values */
    public static function first(array $values, int $length): ?string
    {
        foreach ($values as $value) {
            if (($text = self::clean($value, $length)) !== null) {
                return $text;
            }
        }

        return null;
    }
}
