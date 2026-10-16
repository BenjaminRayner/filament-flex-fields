<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DateTime\ConfiguresClockSelection;
use Bjanczak\FilamentFlexFields\Support\DateTime\Configuration\ClockConfiguration;
use Bjanczak\FilamentFlexFields\Support\DateTime\Values\ClockCodec;
use Closure;

abstract class FlexClockField extends FlexDateTimeField
{
    use ConfiguresClockSelection;

    protected string $view = 'filament-flex-fields::forms.components.date-time.clock-field';

    protected bool|Closure $clockEditable = false;

    protected bool|Closure $clockClearable = false;

    public function clearable(bool|Closure $condition = true): static
    {
        $this->clockClearable = $condition;

        return $this;
    }

    public function isClockClearable(): bool
    {
        return (bool) $this->evaluate($this->clockClearable);
    }

    public function editable(bool|Closure $condition = true): static
    {
        $this->clockEditable = $condition;

        return $this;
    }

    public function isTimeEditable(): bool
    {
        return (bool) $this->evaluate($this->clockEditable);
    }

    public function getClockCodec(): ClockCodec
    {
        return new ClockCodec($this->getStorageFormat(), $this->getDisplayFormat());
    }

    protected function getInvalidTemporalTranslationKey(): string
    {
        return 'filament-flex-fields::date-time.clock_invalid';
    }

    public function getDisplayFormat(): string
    {
        return blank($this->evaluate($this->displayFormat)) && $this->getHourCycle() === 12 && $this->shouldForceLeadingZeros()
            ? str_replace('g:', 'h:', parent::getDisplayFormat()) : parent::getDisplayFormat();
    }

    /** @return string|array{start: ?string, end: ?string}|null */
    public function normalizeState(mixed $state): string|array|null
    {
        $codec = $this->getClockCodec();
        if ($this->getMode() !== DateTimeFieldMode::TimeRange) {
            return $codec->normalize($state);
        }
        if ($this->isEmptyState($state) || ! is_array($state)) {
            return null;
        }
        $range = [];
        foreach (['start', 'end'] as $key) {
            $raw = $state[$key] ?? null;
            $value = $codec->normalize($raw);
            if (filled($raw) && $value === null) {
                return null;
            }
            $range[$key] = $value;
        }

        return ['start' => $range['start'] ?? null, 'end' => $range['end'] ?? null];
    }

    /** @return array<string, mixed> */
    public function getAlpineConfiguration(): array
    {
        return ClockConfiguration::forField($this);
    }

    protected function validateConstraints(string|array $value, Closure $fail): void
    {
        if (is_array($value)) {
            $codec = $this->getClockCodec();
            $start = $codec->canonical($value['start'] ?? null);
            $end = $codec->canonical($value['end'] ?? null);
            if ($start === null || $end === null) {
                $fail(__('filament-flex-fields::default.date_time.validation.incomplete_range'));

                return;
            }
            if ($end < $start) {
                $fail(__('filament-flex-fields::default.date_time.validation.range_order'));

                return;
            }
            $this->validateConstraints($start, $fail);
            $this->validateConstraints($end, $fail);

            return;
        }
        $bounds = ClockConfiguration::bounds($this);
        $clock = $this->getClockCodec()->canonical($value);
        if ($clock === null) {
            $fail(__('filament-flex-fields::default.date_time.validation.invalid'));
        } elseif ($bounds['minValue'] !== null && $clock < $bounds['minValue']) {
            $fail(__('filament-flex-fields::default.date_time.validation.before_min'));
        } elseif ($bounds['maxValue'] !== null && $clock > $bounds['maxValue']) {
            $fail(__('filament-flex-fields::default.date_time.validation.after_max'));
        } elseif (($allowed = $this->getAllowedTimes()) !== null && ! in_array($clock, $allowed, true)) {
            $fail(__('filament-flex-fields::default.date_time.validation.unavailable'));
        }
    }
}
