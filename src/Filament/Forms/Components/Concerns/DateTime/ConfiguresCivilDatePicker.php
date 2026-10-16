<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DateTime;

use Bjanczak\FilamentFlexFields\Support\DateTime\Values\CivilDateCodec;
use Closure;
use InvalidArgumentException;

trait ConfiguresCivilDatePicker
{
    /** @var list<int>|Closure */
    protected array|Closure $disabledWeekdays = [];

    /** @var list<string>|Closure */
    protected array|Closure $disabledDates = [];

    protected bool|Closure $dateEditable = true;

    protected bool|Closure $inlineCalendar = false;

    protected bool|Closure $dateClearable = false;

    protected bool|Closure $fixedCalendarWeeks = true;

    protected bool|Closure $showOutsideCalendarDays = true;

    /** @param list<int>|Closure $days */
    public function disabledWeekdays(array|Closure $days): static
    {
        $this->disabledWeekdays = $days;

        return $this;
    }

    /** @param list<string>|Closure $dates */
    public function disabledDates(array|Closure $dates): static
    {
        $this->disabledDates = $dates;

        return $this;
    }

    public function editable(bool|Closure $condition = true): static
    {
        $this->dateEditable = $condition;

        return $this;
    }

    public function isDateEditable(): bool
    {
        return (bool) $this->evaluate($this->dateEditable);
    }

    public function inline(bool|Closure $condition = true): static
    {
        $this->inlineCalendar = $condition;

        return $this;
    }

    public function clearable(bool|Closure $condition = true): static
    {
        $this->dateClearable = $condition;

        return $this;
    }

    public function fixedWeeks(bool|Closure $condition = true): static
    {
        $this->fixedCalendarWeeks = $condition;

        return $this;
    }

    public function outsideDays(bool|Closure $condition = true): static
    {
        $this->showOutsideCalendarDays = $condition;

        return $this;
    }

    /** @return list<int> */
    public function getDisabledWeekdays(): array
    {
        $days = array_values($this->evaluate($this->disabledWeekdays));
        foreach ($days as $day) {
            if (! is_int($day) || $day < 0 || $day > 6) {
                throw new InvalidArgumentException('Disabled weekdays must be integers from 0 (Sunday) to 6.');
            }
        }

        return array_values(array_unique($days));
    }

    /** @return list<string> */
    public function getDisabledDates(): array
    {
        $dates = array_values($this->evaluate($this->disabledDates));
        if (count($dates) > 10000) {
            throw new InvalidArgumentException('Use an availability provider for more than 10000 disabled dates.');
        }

        // Blackouts are civil days, even when the field stores local datetimes.
        $codec = new CivilDateCodec('Y-m-d', 'Y-m-d');
        foreach ($dates as &$date) {
            $normalized = $codec->canonical($date);
            if ($normalized === null) {
                throw new InvalidArgumentException('A disabled date is not a valid date.');
            }
            $date = $normalized;
        }

        return array_values(array_unique($dates));
    }

    public function isInlineCalendar(): bool
    {
        return (bool) $this->evaluate($this->inlineCalendar);
    }

    public function isDateClearable(): bool
    {
        return (bool) $this->evaluate($this->dateClearable);
    }

    public function hasFixedCalendarWeeks(): bool
    {
        return (bool) $this->evaluate($this->fixedCalendarWeeks);
    }

    public function showsOutsideCalendarDays(): bool
    {
        return (bool) $this->evaluate($this->showOutsideCalendarDays);
    }
}
