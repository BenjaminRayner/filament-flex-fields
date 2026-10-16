<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\DateTime;

use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundComponentPage;
use Filament\Schemas\Components\View;

final class DateTimeNavigation
{
    public static function view(string $current): View
    {
        $pages = [
            'date-time-fields' => ['Overview', 'Every control, real limits and interactive validation.'],
            'date-picker' => ['Date picker', 'Dates, date and time, availability and guided entry.'],
            'date-range-picker' => ['Date range picker', 'Custom shortcuts, timed ranges and responsive layouts.'],
            'time-picker' => ['Time picker', '12/24-hour clocks, appointment slots and time ranges.'],
            'date-periods' => ['Months, years & duration', 'Period selection and elapsed time beyond 24 hours.'],
            'schedule-field' => ['Weekly schedules', 'Opening hours, breaks, overnight shifts and validation.'],
            'timezone-field' => ['Timezones', 'Search, browser defaults, restricted lists and validation.'],
        ];
        $links = [];
        $icons = [
            'date-time-fields' => 'hugeicons-calendar-clock',
            'date-picker' => 'hugeicons-calendar-01',
            'date-range-picker' => 'hugeicons-calendar-sync',
            'time-picker' => 'hugeicons-time-quarter-02',
            'date-periods' => 'hugeicons-calendar-03',
            'schedule-field' => 'hugeicons-chat-delay',
            'timezone-field' => 'hugeicons-time-zone',
        ];
        foreach ($pages as $slug => [$label, $description]) {
            $links[] = ['label' => $label, 'description' => $description, 'icon' => $icons[$slug],
                'url' => FlexFieldsPlaygroundComponentPage::getUrl(configuration: $slug), 'current' => $slug === $current];
        }

        return View::make('filament-flex-fields::playground.date-time.navigation')->columnSpanFull()->viewData(['links' => $links]);
    }
}
