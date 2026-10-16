<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexCivilDateField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexClockField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDurationField;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Contracts\FieldConfigurator;
use Carbon\Carbon;
use Filament\Schemas\Components\Component;

final class DateTimeFieldConfigurator implements FieldConfigurator
{
    public function configure(Component $field, array $config): Component
    {
        if (! $field instanceof FlexDateTimeField) {
            throw new \InvalidArgumentException('Date/time configuration requires a FLEX temporal field.');
        }

        return $this->configureDateTimeField($field, $config);
    }

    public function configureDateTimeField(FlexDateTimeField $field, array $config): FlexDateTimeField
    {
        $field = $field
            ->size($config['size'] ?? config('filament-flex-fields.ui.date_time_size', 'md'))
            ->variant($config['variant'] ?? config('filament-flex-fields.ui.date_time_variant', 'primary'));

        foreach (['highlight_today' => 'highlightToday', 'show_calendar_button' => 'showCalendarButton',
            'focus_outline' => 'focusOutline', 'is_invalid' => 'isInvalid'] as $key => $method) {
            if (array_key_exists($key, $config)) {
                $field->{$method}((bool) $config[$key]);
            }
        }
        foreach (['rounding' => 'rounding', 'calendar_system' => 'calendarSystem',
            'segment_invalid_message' => 'segmentInvalidMessage'] as $key => $method) {
            if (array_key_exists($key, $config)) {
                $field->{$method}($config[$key]);
            }
        }

        if (array_key_exists('granularity', $config) && filled($config['granularity'])) {
            $field->granularity($config['granularity']);
        }

        if (array_key_exists('hour_cycle', $config)) {
            $field->hourCycle((int) $config['hour_cycle']);
        }

        if (array_key_exists('show_seconds', $config)) {
            $field->showSeconds((bool) $config['show_seconds']);
        }

        if (array_key_exists('show_year_segment', $config)) {
            $field->showYearSegment((bool) $config['show_year_segment']);
        }

        if (array_key_exists('month_display', $config) && filled($config['month_display'])) {
            $field->monthDisplay($config['month_display']);
        }

        if (array_key_exists('min_value', $config) && filled($config['min_value'])) {
            $field->minValue($config['min_value']);
        } elseif (array_key_exists('min_date', $config) && filled($config['min_date'])) {
            $field->minDate($config['min_date']);
        }

        if (array_key_exists('max_value', $config) && filled($config['max_value'])) {
            $field->maxValue($config['max_value']);
        } elseif (array_key_exists('max_date', $config) && filled($config['max_date'])) {
            $field->maxDate($config['max_date']);
        }

        if (array_key_exists('display_format', $config) && filled($config['display_format'])) {
            $field->displayFormat($config['display_format']);
        }

        if (array_key_exists('storage_format', $config) && filled($config['storage_format'])) {
            $field->storageFormat($config['storage_format']);
        }

        if (array_key_exists('locale', $config) && filled($config['locale'])) {
            $field->locale($config['locale']);
        }

        if (array_key_exists('time_zone', $config) && filled($config['time_zone'])) {
            $field->timeZone($config['time_zone']);
        }

        if (array_key_exists('force_leading_zeros', $config)) {
            $field->forceLeadingZeros((bool) $config['force_leading_zeros']);
        }

        if (array_key_exists('hide_time_zone', $config)) {
            $field->hideTimeZone((bool) $config['hide_time_zone']);
        }

        if (array_key_exists('hide_time_section', $config)) {
            $field->hideTimeSection((bool) $config['hide_time_section']);
        }

        if (array_key_exists('close_on_select', $config)) {
            $field->closeOnSelect((bool) $config['close_on_select']);
        }

        if (array_key_exists('allow_same_day', $config)) {
            $field->allowSameDay((bool) $config['allow_same_day']);
        }

        if (array_key_exists('range_separator', $config)) {
            $field->rangeSeparator($config['range_separator']);
        }

        if (array_key_exists('week_starts_on', $config)) {
            $field->weekStartsOn((int) $config['week_starts_on']);
        } elseif (array_key_exists('first_day_of_week', $config)) {
            $field->firstDayOfWeek((int) $config['first_day_of_week']);
        }

        if ($field instanceof FlexCivilDateField && ! empty($config['unavailable_dates']) && is_array($config['unavailable_dates'])) {
            $dates = array_values($config['unavailable_dates']);
            $field->isDateUnavailable(fn (Carbon $date): bool => in_array($date->format('Y-m-d'), $dates, true));
        }

        if ($field instanceof FlexCivilDateField || $field instanceof FlexClockField) {
            if (array_key_exists('allowed_times', $config)) {
                $field->allowedTimes($config['allowed_times']);
            }
            if (array_key_exists('clearable', $config)) {
                $field->clearable((bool) $config['clearable']);
            }
            if (array_key_exists('editable', $config)) {
                $field->editable((bool) $config['editable']);
            }
            foreach (['minute_step' => 'minuteStep', 'second_step' => 'secondStep'] as $key => $method) {
                if (array_key_exists($key, $config)) {
                    $field->{$method}((int) $config[$key]);
                }
            }
            if (array_key_exists('default_time', $config)) {
                $field->defaultTime($config['default_time']);
            }
        }
        if ($field instanceof FlexCivilDateField) {
            foreach (['inline' => 'inline', 'fixed_weeks' => 'fixedWeeks', 'outside_days' => 'outsideDays'] as $key => $method) {
                if (array_key_exists($key, $config)) {
                    $field->{$method}((bool) $config[$key]);
                }
            }
            foreach (['disabled_dates' => 'disabledDates', 'disabled_weekdays' => 'disabledWeekdays'] as $key => $method) {
                if (array_key_exists($key, $config)) {
                    $field->{$method}($config[$key]);
                }
            }
        }
        if ($field instanceof FlexDateRangeField) {
            if (array_key_exists('calendar_months', $config)) {
                $field->calendarMonths($config['calendar_months']);
            }
            if (array_key_exists('range_presets', $config)) {
                $field->rangePresets($config['range_presets']);
            }
        }
        if ($field instanceof FlexDurationField && array_key_exists('duration_step', $config)) {
            $field->durationStep((int) $config['duration_step']);
        }

        return $field;
    }
}
