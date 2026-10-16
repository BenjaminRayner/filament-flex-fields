<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Concerns;

use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleDays;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleDisplay;
use Bjanczak\FilamentFlexFields\Support\Schedule\SchedulePolicy;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use InvalidArgumentException;

trait ConfiguresScheduleDisplay
{
    use ConfiguresScheduleWeek;

    /** @var list<string>|Closure */
    protected array|Closure $scheduleDisplayDays = ScheduleDays::ALL;

    protected string|Closure|null $scheduleDisplayLocale = null;

    protected int|Closure $scheduleDisplayHourCycle = 24;

    protected bool|Closure $scheduleDisplayBreaks = true;

    /** @param list<string>|Closure $days */
    public function days(array|Closure $days): static
    {
        $this->scheduleDisplayDays = $days;

        return $this;
    }

    public function locale(string|Closure|null $locale): static
    {
        $this->scheduleDisplayLocale = $locale;

        return $this;
    }

    public function hourCycle(int|Closure $cycle): static
    {
        $this->scheduleDisplayHourCycle = $cycle;

        return $this;
    }

    public function showBreaks(bool|Closure $condition = true): static
    {
        $this->scheduleDisplayBreaks = $condition;

        return $this;
    }

    public function getScheduleDisplayLocale(): string
    {
        return PickerConfiguration::optionalText($this->evaluate($this->scheduleDisplayLocale), 'schedule display locale') ?? app()->getLocale();
    }

    /** @return array{timezone: ?string, active: bool, closed: string, days: list<array{key: string, label: string, short: string, active: bool, hours: string, tooltip: string}>} */
    public function getScheduleDisplay(): array
    {
        $cycle = PickerConfiguration::integer($this->evaluate($this->scheduleDisplayHourCycle), 'schedule display hourCycle');
        if (! in_array($cycle, [12, 24], true)) {
            throw new InvalidArgumentException('Schedule display hourCycle must be 12 or 24.');
        }

        return (new ScheduleDisplay(
            $this->orderScheduleDays(SchedulePolicy::days($this->evaluate($this->scheduleDisplayDays))),
            $this->getScheduleDisplayLocale(),
            $cycle,
            SchedulePolicy::flag($this->evaluate($this->scheduleDisplayBreaks), 'showBreaks'),
        ))->project($this->getState());
    }
}
