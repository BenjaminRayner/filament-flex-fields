<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Transport;

use InvalidArgumentException;

/** Reject malformed/oversized queries before provider, cache identity or rate-limit work. */
final class SearchQueryInput
{
    public static function assert(string $query, ?int $maxCharacters = null): void
    {
        $maxBytes = max(1, (int) config('filament-flex-fields.select.max_search_bytes', 4096));
        $maxCharacters ??= max(1, (int) config('filament-flex-fields.select.max_search_characters', 512));
        if (strlen($query) > $maxBytes || ! mb_check_encoding($query, 'UTF-8') || mb_strlen($query) > $maxCharacters) {
            throw new InvalidArgumentException('Picker search query exceeds its UTF-8 or length budget.');
        }
    }
}
