<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\PriceRange;

use Bjanczak\FilamentFlexFields\Support\PriceRange\RangeConstraints;
use InvalidArgumentException;

trait PresentsPriceRange
{
    public function getRangeConstraints(): RangeConstraints
    {
        return new RangeConstraints($this->getMin(), $this->getMax(), $this->getStep(), $this->isInteger(), $this->getDecimalPlaces());
    }

    /** @param array<string, mixed> $state
     * @return array{min: int|float, max: int|float}
     */
    public function normalizeState(array $state): array
    {
        return $this->getRangeConstraints()->display($state);
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        $size = $this->getSize();
        if (! in_array($size, ['sm', 'md', 'lg'], true)) {
            throw new InvalidArgumentException("Price range size [{$size}] is not supported.");
        }

        return ['fff-price-range-field', 'fff-price-range-field--'.$size, 'fff-rounding-'.$this->getRounding(), 'fff-price-range-field--'.$this->getVariant()];
    }

    /** @return array{minBound: int|float, maxBound: int|float, step: int|float, integer: bool, decimalPlaces: ?int, initialState: array{min: int|float, max: int|float}, histogram: list<int|float>, locale: ?string, prefix: ?string, disabled: bool, readOnly: bool} */
    public function getPriceRangeConfiguration(): array
    {
        $constraints = $this->getRangeConstraints();
        $state = $this->getState();

        return [
            'minBound' => $constraints->min, 'maxBound' => $constraints->max, 'step' => $constraints->step,
            'integer' => $constraints->integer, 'decimalPlaces' => $constraints->decimalPlaces,
            'initialState' => $constraints->display(is_array($state) ? $state : []),
            'histogram' => $this->getHistogram(), 'locale' => $this->getLocale(), 'prefix' => $this->getPrefix(),
            'disabled' => $this->isDisabled(), 'readOnly' => $this->isReadOnly(),
        ];
    }
}
