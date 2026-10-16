<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexSlider;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use InvalidArgumentException;

/** @mixin FlexSlider */
trait PresentsSliderPips
{
    public function shouldShowStepDots(): bool
    {
        $step = $this->getStep() === null ? null : (float) $this->getStep();

        return $this->hasConfiguredStepDots() && ! $this->isVertical() && $step !== null && $step > 0
            && ($this->getMaxValue() - $this->getMinValue()) / $step <= 100;
    }

    /** @return list<float> */
    public function getStepDotRatios(): array
    {
        if (! $this->shouldShowStepDots()) {
            return [];
        }
        $count = (int) round(($this->getMaxValue() - $this->getMinValue()) / (float) $this->getStep());
        $indices = $count <= 10 ? range(0, $count) : array_map(fn (int $index): int => (int) round($index * $count / 10), range(0, 10));

        return array_values(array_unique(array_map(fn (int $index): float => round($this->valueToRatio(min($this->getMaxValue(), $this->getMinValue() + $index * (float) $this->getStep())), 6), $indices)));
    }

    public function stepDotStyle(float $ratio): string
    {
        return '--fff-flex-slider-step-ratio: '.$this->formatRatio($ratio).';';
    }

    public function shouldRenderServerPips(): bool
    {
        return ! $this->isVertical() && $this->getPipsMode() !== null && $this->getPipsFilter() === null && $this->getPipsFormatter() === null && $this->getNonLinearPoints() === null;
    }

    /** @return list<array{ratio: float, label: ?string, size: string}> */
    public function getServerRenderedPips(): array
    {
        if (! $this->shouldRenderServerPips()) {
            return [];
        }
        $mode = $this->getPipsMode();
        $min = (float) $this->getMinValue();
        $max = (float) $this->getMaxValue();
        $values = $this->getPipsValues();
        if ($mode === PipsMode::Steps && $this->getStep() !== null && $this->getStep() > 0) {
            $count = (int) floor(($max - $min) / (float) $this->getStep());
            if ($count > 1000) {
                throw new InvalidArgumentException('Reduce the number of slider pips to at most 1001.');
            }
            $points = array_map(fn (int $index): float => min($max, $min + $index * (float) $this->getStep()), range(0, $count));
        } elseif ($mode === PipsMode::Count && is_numeric($values) && $values >= 2 && $values <= 1001) {
            $count = (int) $values - 1;
            $points = array_map(fn (int $index): float => $min + ($max - $min) * $index / $count, range(0, $count));
        } elseif (is_array($values) && in_array($mode, [PipsMode::Positions, PipsMode::Values], true)) {
            if (count($values) > 1001) {
                throw new InvalidArgumentException('Reduce the number of slider pips to at most 1001.');
            }
            $points = array_map(fn (int|float $value): float => $mode === PipsMode::Positions ? $min + ($max - $min) * $value / 100 : (float) $value, $values);
        } else {
            $points = [$min, $max];
        }
        sort($points);
        $pips = [];
        $previous = null;
        foreach ($points as $value) {
            $value = max($min, min($max, $this->arePipsStepped() ? $this->normalizeNumeric($value) : $value));
            // Unstepped pips describe exact scale positions, independently of selectable increments.
            $ratio = $max > $min ? ($value - $min) / ($max - $min) : 0.0;
            if ($previous !== null) {
                $steps = (int) round(($ratio - $previous) * 100 / max(1, $this->getPipsDensity() ?? 10));
                for ($index = 1; $index < $steps; $index++) {
                    $at = round($previous + ($ratio - $previous) * $index / $steps, 6);
                    $pips[$this->formatRatio($at)] = ['ratio' => $at, 'label' => null, 'size' => 'normal'];
                }
            }
            $label = ($this->getDisplayPrefix() ?? '').$this->formatNumberForLocale($value, $this->getDecimalPlaces()).($this->getDisplaySuffix() ?? '');
            $pips[$this->formatRatio($ratio)] = ['ratio' => $ratio, 'label' => $label, 'size' => $value <= $min || $value >= $max ? 'large' : 'sub'];
            $previous = $ratio;
        }
        $pips = array_values($pips);
        usort($pips, fn (array $a, array $b): int => $a['ratio'] <=> $b['ratio']);

        return $pips;
    }

    public function pipStyle(float $ratio): string
    {
        return '--fff-flex-slider-pip-ratio: '.$this->formatRatio($ratio).';';
    }
}
