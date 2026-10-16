<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\Progress;

use Bjanczak\FilamentFlexFields\Support\Progress\ProgressValue;
use Closure;
use InvalidArgumentException;

trait HasProgressValue
{
    protected float|int|Closure|null $value = null;

    protected float|int|Closure $max = 100;

    public function value(float|int|Closure|null $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function max(float|int|Closure $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function getValue(): float
    {
        return $this->finiteProgressNumber($this->evaluate($this->value) ?? 0);
    }

    public function getMax(): float
    {
        $max = $this->finiteProgressNumber($this->evaluate($this->max));
        if ($max <= 0) {
            throw new InvalidArgumentException('Progress maximum must be positive.');
        }

        return $max;
    }

    public function getProgressValue(): ProgressValue
    {
        return new ProgressValue($this->getValue(), $this->getMax());
    }

    public function getPercentage(): int
    {
        return (int) round($this->getProgressRatio() * 100);
    }

    protected function finiteProgressNumber(mixed $value): float
    {
        if ((! is_float($value) && ! is_int($value)) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException('Progress configuration requires a finite number.');
        }

        return (float) $value;
    }
}
