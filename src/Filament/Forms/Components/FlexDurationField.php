<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;
use Bjanczak\FilamentFlexFields\Enums\DateTimeGranularity;
use Bjanczak\FilamentFlexFields\Support\DateTime\DateTimeAlpineConfiguration;
use Bjanczak\FilamentFlexFields\Support\DateTime\Values\DurationCodec;
use Closure;
use InvalidArgumentException;

class FlexDurationField extends FlexDateTimeField
{
    protected string $view = 'filament-flex-fields::forms.components.date-time.duration-field';

    protected int|Closure $durationStep = 60;

    public function getMode(): DateTimeFieldMode
    {
        return DateTimeFieldMode::Duration;
    }

    protected function getInvalidTemporalTranslationKey(): string
    {
        return 'filament-flex-fields::date-time.duration_invalid';
    }

    public function durationStep(int|Closure $seconds): static
    {
        $this->durationStep = $seconds;

        return $this;
    }

    public function getDurationStep(): int
    {
        $step = $this->evaluate($this->durationStep);
        if (! is_int($step) || $step < 1 || $step > DurationCodec::MAX_SECONDS || (! $this->shouldShowSeconds() && $step % 60 !== 0)) {
            throw new InvalidArgumentException('Duration step must be positive. Without seconds, use a multiple of 60.');
        }

        return $step;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->showCalendar = false;
        $this->showCalendarButton = false;
        $this->hourCycle = 24;
        $this->granularity = DateTimeGranularity::Minute;
        $this->hideTimeZone = true;
    }

    public function getDisplayFormat(): string
    {
        $format = $this->shouldShowSeconds() ? 'H:i:s' : 'H:i';
        $configured = $this->evaluate($this->displayFormat);
        if (filled($configured) && $configured !== $format) {
            throw new InvalidArgumentException('Duration display format follows precision: H:i, or H:i:s with seconds.');
        }

        return $format;
    }

    public function getStorageFormat(): string
    {
        $configured = $this->evaluate($this->storageFormat);
        $format = filled($configured) ? (string) $configured : $this->getDisplayFormat();
        if (! in_array($format, ['H:i', 'H:i:s'], true) || ($this->shouldShowSeconds() && $format !== 'H:i:s')) {
            throw new InvalidArgumentException('Duration storage format must be H:i, or H:i:s when seconds are enabled.');
        }

        return $format;
    }

    public function normalizeState(mixed $state): ?string
    {
        $seconds = DurationCodec::seconds($state);

        return $seconds === null ? null : DurationCodec::format($seconds, $this->getStorageFormat() === 'H:i:s');
    }

    /** @return array{min: int, max: int} */
    public function getDurationBounds(): array
    {
        $rawMin = $this->evaluate($this->getMinValue());
        $rawMax = $this->evaluate($this->getMaxValue());
        $min = $rawMin === null ? 0 : DurationCodec::seconds($rawMin);
        $max = $rawMax === null ? DurationCodec::MAX_SECONDS - ($this->getStorageFormat() === 'H:i' ? 59 : 0) : DurationCodec::seconds($rawMax);
        if ($min === null || $max === null || $min > $max || ($this->getStorageFormat() === 'H:i' && ($min % 60 !== 0 || ($rawMax !== null && $max % 60 !== 0)))) {
            throw new InvalidArgumentException('Invalid duration bounds. Use nonnegative HH:mm[:ss] values with min <= max.');
        }

        return ['min' => $min, 'max' => $max];
    }

    /** @return array<string, mixed> */
    public function getAlpineConfiguration(): array
    {
        if ($this->getIsDateUnavailableCallback() !== null) {
            throw new InvalidArgumentException('Duration fields do not accept date availability callbacks.');
        }

        return [
            ...$this->getDurationBounds(),
            'step' => $this->getDurationStep(),
            'showSeconds' => $this->shouldShowSeconds(),
            'storeSeconds' => $this->getStorageFormat() === 'H:i:s',
            'initialDisplay' => $this->normalizeState($this->resolveInitialStateForAlpine()) ?? '',
            'validationMessages' => [...DateTimeAlpineConfiguration::validationMessages(), 'invalid' => __('filament-flex-fields::date-time.duration_invalid')],
        ];
    }

    protected function validateConstraints(string|array $value, Closure $fail): void
    {
        $seconds = DurationCodec::seconds($value);
        $bounds = $this->getDurationBounds();
        if ($seconds === null) {
            $fail(__('filament-flex-fields::date-time.duration_invalid'));
        } elseif ($seconds < $bounds['min']) {
            $fail(__('filament-flex-fields::default.date_time.validation.before_min'));
        } elseif ($seconds > $bounds['max']) {
            $fail(__('filament-flex-fields::default.date_time.validation.after_max'));
        }
    }
}
