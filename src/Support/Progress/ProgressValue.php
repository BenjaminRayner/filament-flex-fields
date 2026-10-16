<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Progress;

use InvalidArgumentException;

final readonly class ProgressValue
{
    public float $ratio;

    public int $percentage;

    public float $current;

    public function __construct(public float $value, public float $max)
    {
        if (! is_finite($value) || ! is_finite($max) || $max <= 0) {
            throw new InvalidArgumentException('Progress requires finite values and a positive maximum.');
        }
        $this->current = max(0.0, min($value, $max));
        $this->ratio = $this->current / $max;
        $this->percentage = (int) round($this->ratio * 100);
    }
}
