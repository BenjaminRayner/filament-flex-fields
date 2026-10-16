<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NumberStepper;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use Closure;
use InvalidArgumentException;

/** @mixin NumberStepper */
trait ConfiguresStepperValue
{
    protected mixed $minValue = null;

    protected mixed $maxValue = null;

    protected int|float|Closure $step = 1;

    protected bool|Closure $isInteger = true;

    protected bool|Closure $isNullable = false;

    public function minValue(mixed $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    public function maxValue(mixed $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    public function step(int|float|Closure $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function integer(bool|Closure $condition = true): static
    {
        $this->isInteger = $condition;

        return $this;
    }

    public function nullable(bool|Closure $condition = true): static
    {
        $this->isNullable = $condition;

        return $this;
    }

    /** @return int|float|null */
    public function getMinValue(): mixed
    {
        return NumericValue::bound($this->evaluate($this->minValue), 'minValue');
    }

    /** @return int|float|null */
    public function getMaxValue(): mixed
    {
        return NumericValue::bound($this->evaluate($this->maxValue), 'maxValue');
    }

    public function getStep(): int|float
    {
        $value = NumericValue::bound($this->evaluate($this->step), 'step');
        if ($value === null || $value <= 0) {
            throw new InvalidArgumentException('NumberStepper step must be positive.');
        }

        return $value;
    }

    public function isInteger(): bool
    {
        return (bool) $this->evaluate($this->isInteger);
    }

    public function isNullable(): bool
    {
        return (bool) $this->evaluate($this->isNullable);
    }

    /** @return array{min: int|float|null, max: int|float|null, step: int|float} */
    public function getNumericPolicy(): array
    {
        $policy = ['min' => $this->getMinValue(), 'max' => $this->getMaxValue(), 'step' => $this->getStep()];
        if ($policy['min'] !== null && $policy['max'] !== null && $policy['min'] > $policy['max']) {
            throw new InvalidArgumentException('NumberStepper minValue cannot exceed maxValue.');
        }

        return $policy;
    }
}
