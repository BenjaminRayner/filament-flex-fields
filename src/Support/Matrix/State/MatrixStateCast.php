<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Matrix\State;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final readonly class MatrixStateCast implements StateCast
{
    public function __construct(private bool $multiple) {}

    public function get(mixed $state): mixed
    {
        return $this->set($state);
    }

    public function set(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return [];
        }
        if (! is_array($state)) {
            return $state;
        }
        foreach ($state as $key => $value) {
            if (! $this->multiple) {
                $state[$key] = is_int($value) ? (string) $value : $value;

                continue;
            }
            if (is_array($value) && array_is_list($value)) {
                $state[$key] = array_map(static fn (mixed $item): mixed => is_int($item) ? (string) $item : $item, $value);
            }
        }

        return $state;
    }
}
