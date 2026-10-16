<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Concerns;

use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleDays;
use Bjanczak\FilamentFlexFields\Support\Schedule\SchedulePolicy;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;

trait ConfiguresScheduleWeek
{
    protected int|Closure|null $scheduleWeekStartsOn = null;

    protected bool|Closure|null $scheduleRtl = null;

    /** 0 = Sunday, 1 = Monday. Without this option, days() keeps its supplied order. */
    public function weekStartsOn(int|Closure|null $day): static
    {
        $this->scheduleWeekStartsOn = $day;

        return $this;
    }

    /** Null inherits the surrounding page's text direction. */
    public function rtl(bool|Closure|null $condition = true): static
    {
        $this->scheduleRtl = $condition;

        return $this;
    }

    public function getScheduleDirection(): ?string
    {
        $value = $this->evaluate($this->scheduleRtl);

        return $value === null ? null : (SchedulePolicy::flag($value, 'rtl') ? 'rtl' : 'ltr');
    }

    /**
     * @param  list<string>  $days
     * @return list<string>
     */
    protected function orderScheduleDays(array $days): array
    {
        $start = $this->evaluate($this->scheduleWeekStartsOn);

        return $start === null ? $days : ScheduleDays::ordered($days, PickerConfiguration::integer($start, 'weekStartsOn'));
    }
}
