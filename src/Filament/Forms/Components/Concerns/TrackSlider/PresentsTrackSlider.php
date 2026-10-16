<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrackSlider;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TrackSlider;
use Bjanczak\FilamentFlexFields\Support\TrackSlider\TrackSliderValue;

/** @mixin TrackSlider */
trait PresentsTrackSlider
{
    public function assertTrackConfiguration(): void
    {
        TrackSliderValue::assertConfiguration($this->getMin(), $this->getMax(), $this->getStep(), $this->getDecimalPlaces());
    }

    /** @return array{min: int|float, max: int|float, step: int|float, integer: bool, decimalPlaces: ?int, suffix: ?string, disabled: bool, initial: int|float} */
    public function getTrackRuntimeConfiguration(): array
    {
        $min = $this->getMin();
        $max = $this->getMax();
        $step = $this->getStep();
        $precision = $this->getDecimalPlaces();
        $integer = $this->isInteger();
        TrackSliderValue::assertConfiguration($min, $max, $step, $precision);

        return [
            'min' => $min, 'max' => $max, 'step' => $step, 'integer' => $integer,
            'decimalPlaces' => $precision, 'suffix' => $this->getDisplaySuffix(), 'disabled' => $this->isDisabled(),
            'initial' => TrackSliderValue::normalize($this->getState(), $min, $max, $integer, $precision),
        ];
    }
}
