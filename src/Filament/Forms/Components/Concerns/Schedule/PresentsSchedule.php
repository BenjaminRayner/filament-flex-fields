<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Schedule;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Support\Translations;

trait PresentsSchedule
{
    /** Actual TimezoneField owns its catalog/presentation and Select dependencies. */
    public function makeTimezoneControl(): ?TimezoneField
    {
        if (! $this->showsTimezoneSelector()) {
            return null;
        }

        return TimezoneField::make('timezone')->container($this->getContainer())
            ->statePath($this->getStatePath(false).'.timezone')->id($this->getId().'-timezone')->key($this->getKey().'-timezone')
            ->timezones($this->getResolvedTimezoneIdentifiers())->defaultTimezone($this->getDefaultTimezoneIdentifier())
            ->size('md')->variant($this->getVariant())->disabled($this->isDisabled() || $this->isReadOnly());
    }

    /** The shared Switch owns presentation and pointer/keyboard motion. Schedule owns the value. */
    public function makeDaySwitch(string $day): SwitchField
    {
        return SwitchField::make('enabled')->container($this->getContainer())
            ->statePath($this->getStatePath(false).'.days.'.$day.'.enabled')
            ->id($this->getId().'-'.$day.'-enabled')->key($this->getKey().'-'.$day.'-enabled')
            ->label(fn () => Translations::get('filament-flex-fields::default.schedule.toggle_day', ['day' => Translations::get('filament-flex-fields::default.schedule.days.'.$day)]))
            ->inline()->size('sm')->disabled($this->isDisabled() || $this->isReadOnly() || in_array($day, $this->getLockedDays(), true));
    }

    /** @return array<string,mixed> */
    public function getAlpineConfiguration(): array
    {
        $days = $this->getDays();
        $labels = [];
        foreach (['timezone' => 'timezone', 'timezonePlaceholder' => 'timezone_placeholder', 'closed' => 'closed', 'open' => 'open', 'from' => 'from', 'to' => 'to', 'addSlot' => 'add_slot', 'addBreak' => 'add_break', 'removeSlot' => 'remove_slot', 'copyToWeekdays' => 'copy_to_weekdays', 'copyConfirm' => 'copy_confirm', 'slot' => 'slot', 'break' => 'break', 'overnight' => 'overnight', 'moveSlotUp' => 'move_slot_up', 'moveSlotDown' => 'move_slot_down'] as $name => $key) {
            $labels[$name] = __("filament-flex-fields::default.schedule.{$key}");
        }
        $dayLabels = [];
        foreach ($days as $day) {
            $dayLabels[$day] = __("filament-flex-fields::default.schedule.days.{$day}");
        }
        $validation = [];
        foreach (['from_before_to', 'min_slots', 'max_slots', 'overlap'] as $key) {
            $validation[$key] = __("filament-flex-fields::default.schedule.ui.{$key}");
        }

        return ['days' => $days, 'dayLabels' => $dayLabels, 'weekdays' => $this->getWorkdays(), 'timeStep' => $this->getTimeStep(),
            'minSlots' => $this->getMinSlots(), 'maxSlots' => $this->getMaxSlots(), 'lockedDays' => $this->getLockedDays(),
            'requireSlotsForEnabledDays' => $this->shouldRequireSlotsForEnabledDays(), 'validationMessages' => $validation,
            'allowCopyToWeekdays' => $this->shouldAllowCopyToWeekdays(), 'copySourceDay' => $this->getCopySourceDay(),
            'showTimezone' => $this->showsTimezoneSelector(), 'defaultTimezone' => $this->getDefaultTimezoneIdentifier(), 'labels' => $labels];
    }

    /** @return array<string,bool> */
    public function getWrapperClasses(): array
    {
        return ['fff-schedule-field' => true, 'fff-schedule-field--'.$this->getSize() => true, 'fff-schedule-field--'.$this->getVariant() => true];
    }
}
