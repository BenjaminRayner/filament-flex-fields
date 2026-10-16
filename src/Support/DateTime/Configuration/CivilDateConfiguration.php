<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DateTime\Configuration;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexCivilDateField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateRangeField;
use Bjanczak\FilamentFlexFields\Support\DateTime\DateTimeAlpineConfiguration;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableLocaleDirection;
use InvalidArgumentException;

final class CivilDateConfiguration
{
    /** @return array<string, mixed> */
    public static function forField(FlexCivilDateField $field): array
    {
        $codec = $field->getCivilDateCodec();
        if (! $field->hasCalendarTime() && $field->getAllowedTimes() !== null) {
            throw new InvalidArgumentException('Allowed times require a datetime picker or a date range with time enabled.');
        }
        $bounds = self::bounds($field);
        new \DateTimeZone($field->getTimeZone());

        $initial = $field->resolveInitialStateForAlpine();
        $labels = DateTimeAlpineConfiguration::labels();
        foreach (['selected', 'choose_day', 'apply', 'cancel', 'pick_end', 'days', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'this_quarter', 'year_to_date', 'previous', 'next', 'choose_month', 'choose_year', 'clear', 'retry', 'loading', 'availability_error', 'time', 'start_time', 'end_time', 'seconds'] as $key) {
            $labels[$key] = self::text("filament-flex-fields::date-time.{$key}");
        }

        $range = $field->getMode()->value === 'dateRange';
        $canonical = $range && is_array($initial) ? ['start' => $codec->canonical($initial['start'] ?? null), 'end' => $codec->canonical($initial['end'] ?? null)] : $codec->canonical($initial);
        $compactRange = $range && ! $field->hasCalendarTime() && ! $field->isDateEditable() && ! $field->hasExplicitDateDisplayFormat() && str_starts_with($field->getLocale(), 'en');
        $display = $range && is_array($initial) ? $codec->display($initial['start'] ?? null).$field->getRangeSeparator().$codec->display($initial['end'] ?? null) : $codec->display($initial);

        if ($compactRange && is_array($initial)) {
            $start = $codec->parse($initial['start'] ?? null);
            $end = $codec->parse($initial['end'] ?? null);
            if ($start !== null && $end !== null) {
                $display = $start->isSameDay($end) ? $start->format('M j, Y') : ($start->year === $end->year ? $start->format('M j').' – '.$end->format($start->month === $end->month ? 'j, Y' : 'M j, Y') : $start->format('M j, Y').' – '.$end->format('M j, Y'));
            }
        }

        $pattern = CivilDateSegments::pattern($field->getDisplayFormat());
        $segments = $range
            ? [CivilDateSegments::initial($pattern, $codec, is_array($initial) ? ($initial['start'] ?? null) : null), CivilDateSegments::initial($pattern, $codec, is_array($initial) ? ($initial['end'] ?? null) : null)]
            : [CivilDateSegments::initial($pattern, $codec, $initial)];
        $placeholder = implode('', array_column($pattern, 'placeholder'));

        return [
            'segmentPattern' => $pattern,
            'initialSegments' => $segments,
            'compactRangeLabel' => $compactRange,
            'version' => 2,
            'mode' => $field->getMode()->value,
            'rangePresets' => $field instanceof FlexDateRangeField ? $field->getRangePresets() : null,
            'calendarMonths' => $field instanceof FlexDateRangeField ? $field->getCalendarMonths() : 1,
            'rangeSeparator' => $field->getRangeSeparator(),
            'editable' => $field->isDateEditable(),
            'granularity' => $field->getGranularity()->value,
            'locale' => $field->getLocale(),
            'direction' => TranslatableLocaleDirection::resolveDirection($field->getLocale()),
            'calendarIdentifier' => $field->getCalendarIdentifier(),
            'timeZone' => $field->getTimeZone(),
            'hideTimeZone' => $field->shouldHideTimeZone(),
            'hourCycle' => $field->getHourCycle(),
            'forceLeadingZeros' => $field->shouldForceLeadingZeros(),
            'displayFormat' => $field->getDisplayFormat(),
            'storageFormat' => $field->getStorageFormat(),
            'hideTimeSection' => $field->shouldHideTimeSection(),
            'withTime' => $field->hasCalendarTime() && ! $field->shouldHideTimeSection(),
            'storesTime' => $field->hasCalendarTime(),
            'minuteStep' => $field->getGranularity()->value === 'hour' ? 60 : $field->getCalendarMinuteStep(),
            'secondStep' => $field->getCalendarSecondStep(),
            'timeGranularity' => $field->shouldShowSeconds() ? 'second' : $field->getGranularity()->value,
            'defaultTime' => $field->getCalendarDefaultTime(),
            'allowedTimes' => $field->getAllowedTimes(),
            'noTimesMessage' => __('filament-flex-fields::date-time.no_times'),
            'allowSameDay' => $field->shouldAllowSameDay(),
            'minValue' => $bounds['minValue'] === null ? null : substr($bounds['minValue'], 0, 10),
            'maxValue' => $bounds['maxValue'] === null ? null : substr($bounds['maxValue'], 0, 10),
            'minDateTime' => $field->hasCalendarTime() ? $bounds['minValue'] : null,
            'maxDateTime' => $field->hasCalendarTime() ? $bounds['maxValue'] : null,
            'disabledWeekdays' => $field->getDisabledWeekdays(),
            'unavailableDates' => $field->getDisabledDates(),
            'hasDateUnavailable' => $field->getIsDateUnavailableCallback() !== null,
            'showCalendar' => $field->shouldShowCalendar(),
            'showCalendarButton' => $field->shouldShowCalendarButton(),
            'closeOnSelect' => $field->shouldCloseOnSelect(),
            'firstDayOfWeek' => $field->getFirstDayOfWeek(),
            'highlightToday' => $field->shouldHighlightToday(),
            'inline' => $field->isInlineCalendar(),
            'clearable' => $field->isDateClearable(),
            'fixedWeeks' => $field->hasFixedCalendarWeeks(),
            'outsideDays' => $field->showsOutsideCalendarDays(),
            'placeholder' => $field->getPlaceholder() ?? ($range ? $placeholder.$field->getRangeSeparator().$placeholder : $placeholder),
            'initialState' => $initial,
            'canonicalValue' => $canonical,
            'initialDisplay' => $display,
            'segmentInvalidMessage' => $field->getSegmentInvalidMessage(),
            'isInvalid' => $field->getIsInvalid(),
            'labels' => $labels,
            'validationMessages' => DateTimeAlpineConfiguration::validationMessages(),
        ];
    }

    /** @return array{minValue: ?string, maxValue: ?string} */
    public static function bounds(FlexCivilDateField $field): array
    {
        $codec = $field->getCivilDateCodec();
        $minRaw = $field->evaluate($field->getMinValue());
        $maxRaw = $field->evaluate($field->getMaxValue());
        $min = $codec->canonical($minRaw);
        $max = $codec->canonical($maxRaw);
        if (($minRaw !== null && $min === null) || ($maxRaw !== null && $max === null)
            || ($min !== null && $max !== null && $min > $max)) {
            throw new InvalidArgumentException('Invalid civil-date bounds.');
        }

        return ['minValue' => $min, 'maxValue' => $max];
    }

    private static function text(string $key): string
    {
        $text = __($key);
        if (! is_string($text)) {
            throw new \LogicException("Calendar translation [{$key}] must be a string.");
        }

        return $text;
    }
}
