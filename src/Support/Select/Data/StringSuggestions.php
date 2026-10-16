<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/** Atomic string transport: bounds and shape are checked before any result is published. */
final class StringSuggestions
{
    /** @return list<string> */
    public function normalize(mixed $results, int $maxItems = 10000, int $maxBytes = 1048576): array
    {
        if ($results instanceof Arrayable) {
            $results = $results->toArray();
        }

        if (! is_array($results) || count($results) > $maxItems) {
            throw new InvalidArgumentException('Tags search must return a bounded array or Arrayable of string suggestions.');
        }

        $normalized = [];
        $bytes = 0;
        foreach ($results as $value) {
            if ($value === null) {
                continue;
            }
            $text = OptionText::required($value, 'suggestion');
            if (! mb_check_encoding($text, 'UTF-8')) {
                throw new InvalidArgumentException('Tags suggestions require valid UTF-8 text.');
            }
            $bytes += strlen($text);
            if ($bytes > $maxBytes) {
                throw new InvalidArgumentException('Tags search response exceeds its byte budget.');
            }
            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        return array_values(array_unique($normalized, SORT_STRING));
    }
}
