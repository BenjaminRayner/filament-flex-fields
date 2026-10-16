<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns;

use Bjanczak\FilamentFlexFields\Support\Rating\RatingValue;

trait CalculatesRatingFill
{
    /** @return list<int> */
    public function getItemIndexes(): array
    {
        return range(1, RatingValue::assertMaximum($this->getMax()));
    }

    public function getFillPercentageForValue(float|int|null $value, int $index): float
    {
        return RatingValue::fill($value, $index);
    }
}
