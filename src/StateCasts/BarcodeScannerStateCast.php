<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\Barcode\BarcodeStateNormalizer;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** Invalid payloads must reach validation unchanged, never disappear into null. */
final class BarcodeScannerStateCast implements StateCast
{
    public function __construct(private readonly bool $structured) {}

    public function get(mixed $state): mixed
    {
        if ((! $this->structured && is_array($state)) || ! BarcodeStateNormalizer::isValidShape($state)) {
            return $state;
        }

        if ($this->structured && is_string($state) && trim($state) !== '') {
            return ['value' => trim($state), 'format' => null];
        }

        return BarcodeStateNormalizer::dehydrate($state, $this->structured);
    }

    public function set(mixed $state): mixed
    {
        return $this->get($state);
    }
}
