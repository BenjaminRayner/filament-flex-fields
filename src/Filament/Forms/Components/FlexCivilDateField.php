<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;
use Bjanczak\FilamentFlexFields\Enums\DateTimeGranularity;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DateTime\ConfiguresCivilDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DateTime\ConfiguresClockSelection;
use Bjanczak\FilamentFlexFields\Support\DateTime\Configuration\CivilDateConfiguration;
use Bjanczak\FilamentFlexFields\Support\DateTime\DateTimeAlpineConfiguration;
use Bjanczak\FilamentFlexFields\Support\DateTime\Values\CivilDateCodec;
use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/** The inherited public API is compatibility; civil-date execution is owned here. */
abstract class FlexCivilDateField extends FlexDateTimeField
{
    use ConfiguresCivilDatePicker;
    use ConfiguresClockSelection;

    protected string $view = 'filament-flex-fields::forms.components.date-time.civil-date';

    public function getGranularity(): DateTimeGranularity
    {
        $granularity = parent::getGranularity();

        return $this->shouldShowSeconds() && $granularity !== DateTimeGranularity::Day
            ? DateTimeGranularity::Second
            : $granularity;
    }

    public function hasCalendarTime(): bool
    {
        return in_array($this->getMode(), [DateTimeFieldMode::DateTime, DateTimeFieldMode::DateRange], true) && $this->getGranularity() !== DateTimeGranularity::Day;
    }

    public function getCivilDateCodec(): CivilDateCodec
    {
        return new CivilDateCodec($this->getStorageFormat(), $this->getDisplayFormat(), $this->getLocale(), $this->hasCalendarTime() ? 'localDateTime' : 'day');
    }

    public function hasExplicitDateDisplayFormat(): bool
    {
        return filled($this->evaluate($this->displayFormat));
    }

    public function getDisplayFormat(): string
    {
        if (! $this->hasCalendarTime() && blank($this->evaluate($this->displayFormat)) && $this->getMonthDisplay()->value === 'long') {
            return 'F j, Y';
        }

        return $this->hasCalendarTime() && ! $this->hasExplicitDateDisplayFormat() && $this->getHourCycle() === 12 && $this->shouldForceLeadingZeros()
            ? str_replace('g:', 'h:', parent::getDisplayFormat())
            : parent::getDisplayFormat();
    }

    /** @return string|array{start: ?string, end: ?string}|null */
    public function normalizeState(mixed $state): string|array|null
    {
        if ($this->getMode() === DateTimeFieldMode::DateRange) {
            if ($this->isEmptyState($state)) {
                return null;
            }
            if (! is_array($state)) {
                return null;
            }
            $range = [];
            foreach (['start', 'end'] as $key) {
                $value = $state[$key] ?? null;
                $date = $this->getCivilDateCodec()->normalize($value);
                if (filled($value) && $date === null) {
                    return null;
                }
                $range[$key] = $date;
            }

            return ['start' => $range['start'] ?? null, 'end' => $range['end'] ?? null];
        }

        return $this->getCivilDateCodec()->normalize($state);
    }

    /** @return array<string, mixed> */
    public function getAlpineConfiguration(): array
    {
        return CivilDateConfiguration::forField($this);
    }

    protected function validateConstraints(string|array $value, Closure $fail): void
    {

        if (is_array($value)) {
            $codec = $this->getCivilDateCodec();
            $start = $codec->canonical($value['start'] ?? null);
            $end = $codec->canonical($value['end'] ?? null);
            if ($start === null || $end === null) {
                $fail(__('filament-flex-fields::default.date_time.validation.invalid'));

                return;
            }
            if ($end < $start) {
                $fail(__('filament-flex-fields::default.date_time.validation.range_order'));

                return;
            }
            if (! $this->shouldAllowSameDay() && substr($start, 0, 10) === substr($end, 0, 10)) {
                $fail(__('filament-flex-fields::default.date_time.validation.same_day_not_allowed'));

                return;
            }
            $this->validateConstraints($start, $fail);
            $this->validateConstraints($end, $fail);

            return;
        }
        $date = $this->getCivilDateCodec()->parse($value);
        if ($date === null) {
            $fail(__('filament-flex-fields::default.date_time.validation.invalid'));

            return;
        }

        $configuration = CivilDateConfiguration::bounds($this);
        $canonical = $this->getCivilDateCodec()->canonical($value);
        $day = $date->format('Y-m-d');
        if ($configuration['minValue'] !== null && $canonical < $configuration['minValue']) {
            $fail(__('filament-flex-fields::default.date_time.validation.before_min'));

            return;
        }
        if ($configuration['maxValue'] !== null && $canonical > $configuration['maxValue']) {
            $fail(__('filament-flex-fields::default.date_time.validation.after_max'));

            return;
        }

        $callback = $this->getIsDateUnavailableCallback();
        if (in_array($date->dayOfWeek, $this->getDisabledWeekdays(), true)
            || in_array($day, $this->getDisabledDates(), true)
            || ($this->hasCalendarTime() && ($allowed = $this->getAllowedTimes()) !== null && ! in_array($date->format('H:i:s'), $allowed, true))
            || ($callback !== null && (bool) $this->evaluate($callback, ['date' => $date->toMutable()]))) {
            $fail(__('filament-flex-fields::default.date_time.validation.unavailable'));
        }
    }

    /** Validate typed selections against the same server constraints as submission. */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function validateCivilDateSelection(mixed $value): ?string
    {
        if ($this->isDisabled() || $this->isReadOnly()) {
            throw new \InvalidArgumentException('This date field is not editable.');
        }
        $normalized = $this->normalizeState($value);
        if ($normalized === null) {
            return DateTimeAlpineConfiguration::validationMessages()['invalid'];
        }
        $error = null;
        $this->validateConstraints($normalized, static function (string $message) use (&$error): void {
            $error ??= $message;
        });

        return $error;
    }

    /** @return array{start: string, end: string, unavailable: list<string>, status: string} */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getCivilDateAvailability(string $start, string $end): array
    {
        $codec = new CivilDateCodec('Y-m-d', 'Y-m-d');
        $first = $codec->parse($start);
        $last = $codec->parse($end);
        if ($this->isDisabled() || $this->isReadOnly() || $first === null || $last === null
            || $first->greaterThan($last) || $first->diffInDays($last) > 92) {
            throw new \InvalidArgumentException('Invalid calendar availability window.');
        }

        $callback = $this->getIsDateUnavailableCallback();
        $unavailable = [];
        for ($date = $first; $date->lessThanOrEqualTo($last); $date = $date->addDay()) {
            if ($callback !== null && (bool) $this->evaluate($callback, ['date' => $date->toMutable()])) {
                $unavailable[] = $date->format('Y-m-d');
            }
        }

        return ['start' => $start, 'end' => $end, 'unavailable' => $unavailable, 'status' => 'ready'];
    }
}
