<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceLists\State;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** Preserve malformed payloads until validation; never silently drop injected values. */
final class ChecklistStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return $this->normalize($state);
    }

    public function set(mixed $state): mixed
    {
        return $this->normalize($state);
    }

    private function normalize(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return [];
        }
        if (is_string($state)) {
            $decoded = json_decode($state, associative: true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $state = $decoded;
            }
        }
        if (! is_array($state) || ! array_is_list($state)) {
            return $state;
        }
        foreach ($state as $value) {
            if (! is_string($value) && ! is_int($value)) {
                return $state;
            }
        }

        return array_map(strval(...), $state);
    }
}
