<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\Nps\OptionValue;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** Canonical configured keys are stored; malformed payloads survive for validation. */
final class NpsStateCast implements StateCast
{
    /** @param array<array-key, string|int> $options */
    public function __construct(private readonly array $options) {}

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
            return null;
        }
        $identity = OptionValue::identity($state);
        if ($identity !== null) {
            foreach ($this->options as $key => $label) {
                if ((string) $key === $identity) {
                    return $key;
                }
            }
        }

        return $state;
    }
}
