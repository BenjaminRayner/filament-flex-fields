<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slider;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use InvalidArgumentException;

final class SliderPolicy
{
    public static function number(mixed $value): ?float
    {
        $number = NumericValue::parse($value);

        return $number === null ? null : (float) $number;
    }

    public static function assertConfiguration(FlexSlider $field): void
    {
        $min = self::number($field->getMinValue());
        $max = self::number($field->getMaxValue());
        $step = $field->getStep();
        $precision = $field->getDecimalPlaces();
        if ($min === null || $max === null || $max <= $min || ! is_finite($max - $min)
            || ($step !== null && (self::number($step) === null || $step <= 0))
            || ($precision !== null && ($precision < 0 || $precision > 20))) {
            throw new InvalidArgumentException('Slider requires finite ordered bounds, a positive step and precision between 0 and 20.');
        }
        if (! in_array($field->getThumbStyle(), ['pill', 'circle'], true)
            || ! in_array($field->getValuePosition(), ['start', 'center', 'end'], true)
            || ! in_array($field->getValuePlacement(), ['footer', 'header'], true)) {
            throw new InvalidArgumentException('Unknown slider thumb style or value position.');
        }
        if ($field->getStretchLimit() < 1 || $field->getStretchLimit() > 80 || ! is_finite($field->getStretchLimit())
            || $field->getDetentDistance() < 0 || $field->getDetentDistance() > 32 || ! is_finite($field->getDetentDistance())
            || ($field->getLargeStep() !== null && ($field->getLargeStep() <= 0 || ! is_finite($field->getLargeStep())))) {
            throw new InvalidArgumentException('Invalid slider motion or keyboard configuration.');
        }
        foreach ([$field->getMinDifference(), $field->getMaxDifference()] as $gap) {
            if ($gap !== null && (self::number($gap) === null || $gap < 0)) {
                throw new InvalidArgumentException('Slider handle gaps must be finite nonnegative numbers.');
            }
        }
        if ($field->getMinDifference() !== null && $field->getMaxDifference() !== null && $field->getMinDifference() > $field->getMaxDifference()) {
            throw new InvalidArgumentException('Minimum slider gap exceeds maximum gap.');
        }
        $padding = $field->getRangePadding();
        $padding = $padding === null ? [0, 0] : (is_array($padding) ? $padding : [$padding, $padding]);
        if (! array_is_list($padding) || count($padding) !== 2 || self::number($padding[0]) === null || self::number($padding[1]) === null || $padding[0] < 0 || $padding[1] < 0) {
            throw new InvalidArgumentException('Slider padding requires two finite nonnegative values.');
        }
        $points = $field->getNonLinearPoints() ?? [];
        if (count($points) > 128) {
            throw new InvalidArgumentException('Slider non-linear points exceed the supported limit.');
        }
        $ordered = [];
        foreach ($points as $position => $raw) {
            $value = is_array($raw) ? ($raw[0] ?? null) : $raw;
            if (preg_match('/^\d+(?:\.\d+)?%$/D', (string) $position) !== 1 || (float) $position <= 0 || (float) $position >= 100
                || self::number($value) === null || $value <= $min || $value >= $max
                || (is_array($raw) && (! array_is_list($raw) || count($raw) !== 2 || self::number($raw[1]) === null || $raw[1] <= 0))) {
                throw new InvalidArgumentException('Invalid slider non-linear point.');
            }
            $ordered[(string) (float) $position] = (float) $value;
        }
        uksort($ordered, fn (string|int $a, string|int $b): int => (float) $a <=> (float) $b);
        $previous = $min;
        foreach ($ordered as $value) {
            if ($value <= $previous) {
                throw new InvalidArgumentException('Slider non-linear values must strictly increase.');
            }
            $previous = $value;
        }
        if ($button = $field->getIconButtonConfiguration()) {
            if ($field->isRangeState() || ! in_array($button['position'], ['start', 'end'], true)) {
                throw new InvalidArgumentException('An icon button requires one handle and a start/end position.');
            }
            foreach ([$field->getIconButtonValue(), $field->getIconButtonRestoreValue(), $button['value'], $button['restoreValue']] as $value) {
                if (self::number($value) === null || $value < $field->getMinValueWithPadding() || $value > $field->getMaxValueWithPadding()) {
                    throw new InvalidArgumentException('Icon button values must be within the selectable slider range.');
                }
            }
            if ($button['value'] === $button['restoreValue']) {
                throw new InvalidArgumentException('Icon button restore value must differ from its target value.');
            }
        }
        if ($field->getMinValueWithPadding() > $field->getMaxValueWithPadding()) {
            throw new InvalidArgumentException('Slider padding must leave a selectable range.');
        }
    }

    public static function validState(FlexSlider $field, mixed $state): bool
    {
        if ($state === null || $state === '') {
            return true;
        }
        if (is_array($state) && (! array_is_list($state) || count($state) < 1 || count($state) > 128)) {
            return false;
        }
        $values = is_array($state) ? $state : [$state];
        if ($field->shouldUseRangeHandles() && count($values) !== 2) {
            return false;
        }
        $previous = null;
        $unconstrained = str_contains($field->getBehaviorForJs(), 'unconstrained');
        foreach ($values as $value) {
            $number = self::number($value);
            if ($number === null || $number < $field->getMinValueWithPadding() || $number > $field->getMaxValueWithPadding()) {
                return false;
            }
            if ($previous !== null && ! $unconstrained) {
                $gap = $number - $previous;
                if ($gap < 0 || ($field->getMinDifference() !== null && $gap < $field->getMinDifference())
                    || ($field->getMaxDifference() !== null && $gap > $field->getMaxDifference())) {
                    return false;
                }
            }
            $previous = $number;
        }

        return true;
    }
}
