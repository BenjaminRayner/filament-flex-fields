<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Support\DateTime\Configuration\ClockConfiguration;

/** Compatibility entry point for the shared FLEX clock input and menu. */
class FlexTimeSegmentsField extends FlexTimeField
{
    public function getVariant(): string
    {
        return $this->evaluate($this->variant) === 'soft' ? 'soft' : parent::getVariant();
    }

    public function getMinuteStep(): int
    {
        return $this->getCalendarMinuteStep();
    }

    public function getResolvedMinValue(): ?string
    {
        $value = ClockConfiguration::bounds($this)['minValue'];

        return $value === null ? null : $this->getClockCodec()->format($value, $this->getStorageFormat());
    }

    public function getResolvedMaxValue(): ?string
    {
        $value = ClockConfiguration::bounds($this)['maxValue'];

        return $value === null ? null : $this->getClockCodec()->format($value, $this->getStorageFormat());
    }

    public function withRecommendedDefaults(): static
    {
        return parent::withRecommendedDefaults()->hourCycle(24)->minuteStep(15);
    }
}
