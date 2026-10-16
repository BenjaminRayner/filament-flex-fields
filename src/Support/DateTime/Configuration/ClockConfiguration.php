<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DateTime\Configuration;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexClockField;
use Bjanczak\FilamentFlexFields\Support\DateTime\DateTimeAlpineConfiguration;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableLocaleDirection;
use InvalidArgumentException;

final class ClockConfiguration
{
    /** @return array<string, mixed> */
    public static function forField(FlexClockField $field): array
    {
        if ($field->getIsDateUnavailableCallback() !== null) {
            throw new InvalidArgumentException('Date availability callbacks require a date field. Clock fields support local time bounds.');
        }
        $initial = $field->resolveInitialStateForAlpine();
        new \DateTimeZone($field->getTimeZone());
        $codec = $field->getClockCodec();
        $range = $field->getMode()->value === 'timeRange';
        $values = $range && is_array($initial) ? [$initial['start'] ?? null, $initial['end'] ?? null] : [$initial];

        return [
            ...self::bounds($field),
            'mode' => $field->getMode()->value,
            'storageFormat' => $field->getStorageFormat(),
            'displayFormat' => $field->getDisplayFormat(),
            'locale' => $field->getLocale(),
            'direction' => TranslatableLocaleDirection::resolveDirection($field->getLocale()),
            'hourCycle' => $field->getHourCycle(),
            'timeZone' => $field->getTimeZone(),
            'hideTimeZone' => $field->shouldHideTimeZone(),
            'forceLeadingZeros' => $field->shouldForceLeadingZeros(),
            'editable' => $field->isTimeEditable(),
            'clearable' => $field->isClockClearable(),
            'menuError' => __('filament-flex-fields::date-time.menu_error'),
            'segmentInvalidMessage' => $field->getSegmentInvalidMessage(),
            'isInvalid' => $field->getIsInvalid(),
            'minuteStep' => $field->getGranularity()->value === 'hour' ? 60 : $field->getCalendarMinuteStep(),
            'secondStep' => $field->getCalendarSecondStep(),
            'showSeconds' => $field->shouldShowSeconds(),
            'defaultTime' => $field->getCalendarDefaultTime(),
            'allowedTimes' => $field->getAllowedTimes(),
            'noTimesMessage' => __('filament-flex-fields::date-time.no_times'),
            'initialDisplay' => array_map(fn ($value): string => $codec->format($value, $field->getDisplayFormat()), $values),
            'placeholder' => $field->getPlaceholder() ?? __('filament-flex-fields::default.date_time.placeholder_time'),
            'validationMessages' => [...DateTimeAlpineConfiguration::validationMessages(), 'invalid' => __('filament-flex-fields::date-time.clock_invalid')],
        ];
    }

    /** @return array{minValue: ?string, maxValue: ?string} */
    public static function bounds(FlexClockField $field): array
    {
        $codec = $field->getClockCodec();
        $rawMin = $field->evaluate($field->getMinValue());
        $rawMax = $field->evaluate($field->getMaxValue());
        $min = $codec->canonical($rawMin);
        $max = $codec->canonical($rawMax);
        if (($rawMin !== null && $min === null) || ($rawMax !== null && $max === null) || ($min !== null && $max !== null && $min > $max)) {
            throw new InvalidArgumentException('Invalid clock bounds. Use min <= max within the same local day.');
        }

        return ['minValue' => $min, 'maxValue' => $max];
    }
}
