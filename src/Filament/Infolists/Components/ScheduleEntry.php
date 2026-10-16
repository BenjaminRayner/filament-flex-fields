<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Infolists\Components;

use Bjanczak\FilamentFlexFields\Filament\Concerns\ConfiguresScheduleDisplay;
use Bjanczak\FilamentFlexFields\Filament\Concerns\EmitsFlexDisplayAssets;
use Closure;
use Filament\Infolists\Components\Entry;

class ScheduleEntry extends Entry
{
    use ConfiguresScheduleDisplay;
    use EmitsFlexDisplayAssets;

    protected string $view = 'filament-flex-fields::infolists.components.schedule-entry';

    protected bool|Closure $scheduleClosedDays = true;

    protected bool|Closure $scheduleTimezoneVisible = true;

    protected string|Closure|null $scheduleHeading = null;

    protected string|Closure|null $scheduleDescription = null;

    public function showClosedDays(bool|Closure $condition = true): static
    {
        $this->scheduleClosedDays = $condition;

        return $this;
    }

    public function showTimezone(bool|Closure $condition = true): static
    {
        $this->scheduleTimezoneVisible = $condition;

        return $this;
    }

    public function heading(string|Closure|null $heading): static
    {
        $this->scheduleHeading = $heading;

        return $this;
    }

    public function description(string|Closure|null $description): static
    {
        $this->scheduleDescription = $description;

        return $this;
    }

    public function getScheduleHeading(): ?string
    {
        return $this->evaluate($this->scheduleHeading);
    }

    public function getScheduleDescription(): ?string
    {
        return $this->evaluate($this->scheduleDescription);
    }

    public function shouldShowClosedDays(): bool
    {
        return (bool) $this->evaluate($this->scheduleClosedDays);
    }

    public function shouldShowTimezone(): bool
    {
        return (bool) $this->evaluate($this->scheduleTimezoneVisible);
    }

    protected function flexDisplayComponentId(): string
    {
        return 'schedule-entry';
    }
}
