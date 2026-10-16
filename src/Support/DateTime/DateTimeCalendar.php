<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DateTime;

class DateTimeCalendar
{
    /**
     * Resolve an @internationalized/date calendar identifier from locale and optional override.
     */
    public static function resolveIdentifier(?string $locale, ?string $explicit = null): ?string
    {
        if (filled($explicit)) {
            return self::normalizeIdentifier($explicit);
        }

        if (! filled($locale)) {
            return null;
        }

        $normalized = str_replace('_', '-', trim($locale));

        if (preg_match('/-u-ca-([\w-]+)/i', $normalized, $matches) !== 1) {
            return null;
        }

        return self::normalizeIdentifier($matches[1]);
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));

        $normalized = match ($identifier) {
            'gregorian', 'gregory' => 'gregory',
            'islamic' => 'islamic-civil',
            default => $identifier,
        };

        if (! in_array($normalized, ['gregory', 'buddhist', 'coptic', 'ethiopic', 'ethioaa',
            'hebrew', 'indian', 'islamic-civil', 'islamic-tbla', 'islamic-umalqura',
            'japanese', 'persian', 'roc'], true)) {
            throw new \InvalidArgumentException('Unsupported calendar system: '.$identifier);
        }

        return $normalized;
    }
}
