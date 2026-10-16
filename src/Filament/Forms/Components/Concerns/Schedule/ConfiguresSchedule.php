<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Schedule;

use Bjanczak\FilamentFlexFields\Filament\Concerns\ConfiguresScheduleWeek;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleDays;
use Bjanczak\FilamentFlexFields\Support\Schedule\SchedulePolicy;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Timezones;
use Closure;
use InvalidArgumentException;

trait ConfiguresSchedule
{
    use ConfiguresScheduleWeek;

    protected string|Closure $variant = 'primary';

    /** @var list<string>|Closure */
    protected array|Closure $days = ScheduleDays::ALL;

    protected string|Closure|null $timezone = 'UTC';

    protected int|Closure $timeStep = 5;

    protected int|Closure $minSlots = 1;

    protected int|Closure $maxSlots = 10;

    /** @var list<string>|Closure */
    protected array|Closure $lockedDays = [];

    /** @var array<string, mixed>|Closure|null Authoritative state, never a browser-provided snapshot. */
    protected array|Closure|null $lockedSchedule = null;

    protected bool|Closure $allowCopyToWeekdays = true;

    protected string|Closure $copySourceDay = 'mon';

    /** @var list<string>|Closure|null */
    protected array|Closure|null $workdays = null;

    protected bool|Closure $requireSlotsForEnabledDays = true;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    /** @param list<string>|Closure $days */
    public function days(array|Closure $days): static
    {
        $this->days = $days;

        return $this;
    }

    public function timezone(string|Closure|null $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function timeStep(int|Closure $minutes): static
    {
        $this->timeStep = $minutes;

        return $this;
    }

    public function minSlots(int|Closure $count): static
    {
        $this->minSlots = $count;

        return $this;
    }

    public function maxSlots(int|Closure $count): static
    {
        $this->maxSlots = $count;

        return $this;
    }

    /** @param list<string>|Closure $days */
    public function lockedDays(array|Closure $days): static
    {
        $this->lockedDays = $days;

        return $this;
    }

    /** @param array<string, mixed>|Closure $schedule Use for custom fills or non-model storage. */
    public function lockedScheduleUsing(array|Closure $schedule): static
    {
        $this->lockedSchedule = $schedule;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getLockedSchedule(): array
    {
        if ($this->lockedSchedule !== null) {
            $state = $this->evaluate($this->lockedSchedule);
            if (! is_array($state)) {
                throw new InvalidArgumentException('Locked schedule must resolve to an authoritative array.');
            }
        } else {
            $record = $this->getRecord();
            $state = $record === null ? $this->getDefaultState() : data_get($record, $this->getName());
        }

        return $this->normalizeState($state);
    }

    public function allowCopyToWeekdays(bool|Closure $condition = true): static
    {
        $this->allowCopyToWeekdays = $condition;

        return $this;
    }

    public function copySourceDay(string|Closure $day): static
    {
        $this->copySourceDay = $day;

        return $this;
    }

    /** @param list<string>|Closure $days */
    public function workdays(array|Closure $days): static
    {
        $this->workdays = $days;

        return $this;
    }

    public function requireSlotsForEnabledDays(bool|Closure $condition = true): static
    {
        $this->requireSlotsForEnabledDays = $condition;

        return $this;
    }

    public function getVariant(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->variant), ['primary', 'secondary', 'soft', 'flat'], 'schedule variant');
    }

    /** @return list<string> */
    public function getDays(): array
    {
        return $this->orderScheduleDays(SchedulePolicy::days($this->evaluate($this->days)));
    }

    public function showsTimezoneSelector(): bool
    {
        return $this->evaluate($this->timezone) !== null;
    }

    public function getDefaultTimezoneIdentifier(): ?string
    {
        $value = $this->evaluate($this->timezone);
        if ($value === null) {
            return null;
        }
        $configured = PickerConfiguration::optionalText($value, 'schedule timezone');
        $timezone = $configured === null ? 'UTC' : trim($configured);
        $allowed = $this->getResolvedTimezoneIdentifiers();

        return in_array($timezone, $allowed, true) ? $timezone : ($allowed[0] ?? 'UTC');
    }

    public function getTimeStep(): int
    {
        return max(1, min(60, PickerConfiguration::integer($this->evaluate($this->timeStep), 'schedule timeStep')));
    }

    public function getMinSlots(): int
    {
        return SchedulePolicy::slots($this->evaluate($this->minSlots), 'minSlots', 0);
    }

    public function getMaxSlots(): int
    {
        $maximum = SchedulePolicy::slots($this->evaluate($this->maxSlots), 'maxSlots', 1);
        if ($maximum < $this->getMinSlots()) {
            throw new InvalidArgumentException('Schedule maxSlots must be at least minSlots.');
        }

        return $maximum;
    }

    /** @return list<string> */
    public function getLockedDays(): array
    {
        return array_values(array_intersect(SchedulePolicy::days($this->evaluate($this->lockedDays), allowEmpty: true), $this->getDays()));
    }

    public function isDayLocked(string $day): bool
    {
        return in_array(strtolower(trim($day)), $this->getLockedDays(), true);
    }

    public function shouldAllowCopyToWeekdays(): bool
    {
        return SchedulePolicy::flag($this->evaluate($this->allowCopyToWeekdays), 'allowCopyToWeekdays');
    }

    public function getCopySourceDay(): string
    {
        $day = strtolower(trim(PickerConfiguration::text($this->evaluate($this->copySourceDay), 'copy source day')));
        if (! in_array($day, ScheduleDays::ALL, true)) {
            throw new InvalidArgumentException("Invalid schedule copy source day [{$day}].");
        }

        return $day;
    }

    /** @return list<string> */
    public function getWorkdays(): array
    {
        return SchedulePolicy::days($this->evaluate($this->workdays) ?? ScheduleDays::WEEKDAYS, fallbackWeekdays: true);
    }

    public function shouldRequireSlotsForEnabledDays(): bool
    {
        return SchedulePolicy::flag($this->evaluate($this->requireSlotsForEnabledDays), 'requireSlotsForEnabledDays');
    }

    /** @return list<string> */
    public function getResolvedTimezoneIdentifiers(): array
    {
        return Timezones::resolve(null, []);
    }

    /** @return list<array{id: string, label: string, offset: string}> */
    public function getTimezoneOptionsForJs(): array
    {
        return array_values(array_map(static fn (array $zone): array => ['id' => $zone['id'], 'label' => $zone['label'], 'offset' => $zone['offset']], Timezones::metadata(null, [])));
    }

    /** @return list<array{value:string,label:string}> */
    public function getTimeOptions(): array
    {
        return SchedulePolicy::times($this->getTimeStep());
    }
}
