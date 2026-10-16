<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexSlider;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Bjanczak\FilamentFlexFields\Support\Slider\SliderPolicy;

/** @mixin FlexSlider */
trait PresentsSlider
{
    protected function resolveStateForChrome(): mixed
    {
        try {
            $state = $this->getState();
        } catch (\Throwable) {
            $state = null;
        }
        if ($this->shouldUseRangeHandles() && (! is_array($state) || count($state) !== 2)) {
            $default = $this->getDefaultState();

            return is_array($default) && count($default) === 2 ? $default : [$this->getMinValue(), $this->getMaxValue()];
        }

        return $state === null || $state === '' ? $this->getDefaultState() : $state;
    }

    /** @return list<float> */
    public function getNormalizedStateValues(): array
    {
        $state = $this->resolveStateForChrome();
        $values = is_array($state) && array_is_list($state) && count($state) <= 128 ? $state : [$state];

        return array_map(fn (mixed $value): float => min((float) $this->getMaxValue(), max((float) $this->getMinValue(), $this->normalizeNumeric(SliderPolicy::number($value) ?? $this->getMinValue()))), $values ?: [$this->getMinValue()]);
    }

    public function isRangeState(): bool
    {
        return count($this->getNormalizedStateValues()) > 1;
    }

    public function valueToPercent(float|int $value): float
    {
        return $this->valueToRatio($value) * 100;
    }

    public function valueToRatio(float|int $value): float
    {
        $points = [[0.0, (float) $this->getMinValue()], [1.0, (float) $this->getMaxValue()]];
        foreach ($this->getNonLinearPoints() ?? [] as $position => $at) {
            $points[] = [(float) rtrim((string) $position, '%') / 100, (float) (is_array($at) ? $at[0] : $at)];
        }
        usort($points, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $value = $this->normalizeNumeric($value);
        for ($index = 1; $index < count($points); $index++) {
            [$start, $low] = $points[$index - 1];
            [$end, $high] = $points[$index];
            if ($value <= $high && $high > $low) {
                return max(0.0, min(1.0, $start + ($value - $low) / ($high - $low) * ($end - $start)));
            }
        }

        return $value <= $this->getMinValue() ? 0.0 : 1.0;
    }

    /** @return list<float> */
    public function getInitialValueRatios(): array
    {
        return array_map($this->valueToRatio(...), $this->getNormalizedStateValues());
    }

    public function normalizeNumeric(float|int $value): float
    {
        if ($this->getDecimalPlaces() !== null) {
            return round($value, min(20, max(0, $this->getDecimalPlaces())));
        }
        $step = $this->getStep() === null ? null : (float) $this->getStep();

        return $step !== null && $step > 0 ? round(round($value / $step) * $step, $this->stepDecimalCount()) : (float) $value;
    }

    protected function stepDecimalCount(): int
    {
        $step = $this->getStep() === null ? null : (float) $this->getStep();
        $text = rtrim(rtrim(sprintf('%.10F', (float) ($step ?? 0)), '0'), '.');

        return str_contains($text, '.') ? strlen(explode('.', $text)[1]) : 0;
    }

    public function formatDisplayValue(float|int|null $value = null): string
    {
        if ($value === null && $this->isRangeState()) {
            return implode(' – ', array_map($this->formatDisplayValue(...), $this->getNormalizedStateValues()));
        }
        $number = $this->normalizeNumeric($value ?? $this->getNormalizedStateValues()[0]);
        $precision = $this->getDecimalPlaces();
        if ($precision !== null) {
            $text = $this->formatNumberForLocale($number, $precision);
        } elseif ($this->stepDecimalCount() === 0 && $this->getStep() !== null) {
            $text = $this->formatNumberForLocale(round($number), 0);
        } elseif ($this->getLocale() !== null) {
            $text = $this->formatNumberForLocale($number, null);
        } else {
            $text = rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');
        }

        return ($this->getDisplayPrefix() ?? '').$text.($this->getDisplaySuffix() ?? '');
    }

    public function formatRatio(float $ratio): string
    {
        return rtrim(rtrim(sprintf('%.6F', max(0, min(1, $ratio))), '0'), '.');
    }

    public function thumbChromeVariables(float $value): string
    {
        return '--fff-flex-slider-value-ratio: '.$this->formatRatio($this->valueToRatio($value)).';';
    }

    /** @return array<bool>|false */
    public function resolveConnectForChrome(): array|false
    {
        if ($this->getFillTrack() !== null) {
            return $this->getFillTrack();
        }
        if (! $this->shouldAutoFill()) {
            return false;
        }
        $count = count($this->getNormalizedStateValues());

        return array_map(fn (int $index): bool => $count === 1 ? $index === 0 : $index > 0 && $index < $count, range(0, $count));
    }

    /** @return list<array{type: string, startRatio: float, endRatio: float}> */
    public function getInitialFillSegments(): array
    {
        $ratios = $this->getInitialValueRatios();
        $stops = [0.0, ...$ratios, 1.0];
        $segments = [];
        foreach ($this->resolveConnectForChrome() ?: [] as $index => $visible) {
            if ($visible && isset($stops[$index + 1])) {
                $segments[] = ['type' => $this->resolveFillSegmentType($stops[$index], count($ratios)), 'startRatio' => $stops[$index], 'endRatio' => $stops[$index + 1]];
            }
        }

        return $segments;
    }

    public function resolveFillSegmentType(float $startRatio, int $handleCount): string
    {
        return $handleCount === 1 && $startRatio <= 0.0001 ? 'from-min' : 'between';
    }

    /** @param array{type: string, startRatio: float, endRatio: float} $segment */
    public function fillSegmentClass(array $segment): string
    {
        return $this->fillSegmentModifierClass($segment);
    }

    /** @param array{type: string, startRatio: float, endRatio: float} $segment */
    public function fillSegmentModifierClass(array $segment): string
    {
        return 'fff-flex-slider__fill--'.$segment['type'];
    }

    /** @param array{type: string, startRatio: float, endRatio: float} $segment */
    public function fillSegmentVariables(array $segment): string
    {
        return $segment['type'] === 'between'
            ? '--fff-flex-slider-fill-start: '.$this->formatRatio($segment['startRatio']).'; --fff-flex-slider-fill-end: '.$this->formatRatio($segment['endRatio']).';'
            : '--fff-flex-slider-value-ratio: '.$this->formatRatio($segment['endRatio']).';';
    }
}
