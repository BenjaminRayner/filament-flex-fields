<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\DateTimeGranularity;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Enums\MonthDisplay;
use Bjanczak\FilamentFlexFields\Support\DateTime\Values\CivilDateCodec;
use Bjanczak\FilamentFlexFields\Support\DateTime\Values\ClockCodec;
use Bjanczak\FilamentFlexFields\Support\DateTime\Values\DurationCodec;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleDays;
use DateTimeZone;
use Throwable;

final class TemporalConfigurationContract
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config): void
    {
        if ($type === FieldType::Schedule) {
            self::schedule($config);

            return;
        }
        if (! in_array($type, [FieldType::Date, FieldType::Time, FieldType::DateTime, FieldType::DateRange,
            FieldType::Duration, FieldType::TimeRange, FieldType::Month, FieldType::Year], true)) {
            return;
        }
        ConfigurationRules::choice($config, 'granularity', array_column(DateTimeGranularity::cases(), 'value'));
        ConfigurationRules::choice($config, 'hour_cycle', [12, 24, '12', '24']);
        ConfigurationRules::choice($config, 'month_display', array_column(MonthDisplay::cases(), 'value'));
        ConfigurationRules::choice($config, 'time_picker', ['segmented', 'segments', 'dropdown']);
        ConfigurationRules::integer($config, 'first_day_of_week', 0, 6);
        ConfigurationRules::integer($config, 'week_starts_on', 0, 6);
        ConfigurationRules::integer($config, 'duration_step', 1);
        foreach (['hour_cycle', 'first_day_of_week', 'minute_step', 'range_separator'] as $key) {
            ConfigurationRules::nonNull($config, $key);
        }
        foreach (['minute_step', 'second_step'] as $key) {
            ConfigurationRules::integer($config, $key, 1, 60);
            if (isset($config[$key]) && 60 % (int) $config[$key] !== 0) {
                ConfigurationRules::fail($key, 'must divide 60');
            }
        }
        foreach (['highlight_today', 'show_calendar_button', 'focus_outline', 'is_invalid', 'clearable', 'editable', 'inline', 'fixed_weeks', 'outside_days'] as $key) {
            ConfigurationRules::boolean($config, $key);
        }
        foreach (['display_format', 'storage_format', 'calendar_system'] as $key) {
            ConfigurationRules::text($config, $key, 128);
        }
        ConfigurationRules::text($config, 'locale', 128, '/^[A-Za-z]{2,8}(?:[-_][A-Za-z0-9]{1,8})*$/D');
        self::timezone($config, 'time_zone');
        ConfigurationRules::identifiers($config, 'disabled_weekdays', [0, 1, 2, 3, 4, 5, 6], 7);
        $dateCodec = new CivilDateCodec('Y-m-d', 'Y-m-d');
        foreach (['disabled_dates', 'unavailable_dates'] as $key) {
            foreach (ConfigurationRules::items($config, $key) ?? [] as $date) {
                if (! is_string($date) || $dateCodec->canonical($date) !== $date) {
                    ConfigurationRules::fail($key, 'requires real dates in YYYY-MM-DD format');
                }
            }
        }
        $times = ConfigurationRules::items($config, 'allowed_times', 1440);
        foreach ($times ?? [] as $time) {
            self::clock($time, 'allowed_times');
        }
        if (in_array($type, [FieldType::Time, FieldType::TimeRange, FieldType::Duration], true)) {
            foreach (['disabled_dates', 'unavailable_dates', 'disabled_weekdays'] as $key) {
                if (! empty($config[$key])) {
                    ConfigurationRules::fail($key, 'requires a civil date field');
                }
            }
        }
        if (isset($config['default_time'])) {
            $default = self::clock($config['default_time'], 'default_time');
            if ($times !== null && ! in_array($default, array_map(static fn ($time): string => self::clock($time, 'allowed_times'), $times), true)) {
                ConfigurationRules::fail('default_time', 'must be one of the allowed times');
            }
        }
        if ($type === FieldType::DateRange) {
            ConfigurationRules::choice($config, 'calendar_months', [1, 2, 'auto']);
            foreach (ConfigurationRules::items($config, 'range_presets', 30) ?? [] as $preset) {
                if (! is_array($preset) || array_diff(array_keys($preset), ['label', 'start', 'end']) !== []) {
                    ConfigurationRules::fail('range_presets', 'requires label, start and end records');
                }
                ConfigurationRules::text($preset, 'label', 512);
                $start = $dateCodec->canonical($preset['start'] ?? null);
                $end = $dateCodec->canonical($preset['end'] ?? null);
                if (! is_string($preset['label'] ?? null) || trim($preset['label']) === '' || $start === null || $end === null || $start > $end) {
                    ConfigurationRules::fail('range_presets', 'requires named ordered real date ranges');
                }
            }
        }
        self::bounds($type, $config);
    }

    /** @param array<string, mixed> $config */
    public static function timezone(array $config, string $key): void
    {
        ConfigurationRules::text($config, $key, 128);
        if (! isset($config[$key])) {
            return;
        }
        try {
            new DateTimeZone($config[$key]);
        } catch (Throwable) {
            ConfigurationRules::fail($key, 'requires a valid timezone');
        }
    }

    private static function clock(mixed $value, string $key): string
    {
        if (! is_string($value) || preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $value) !== 1) {
            ConfigurationRules::fail($key, 'requires HH:mm or HH:mm:ss');
        }

        return strlen($value) === 5 ? $value.':00' : $value;
    }

    /** @param array<string, mixed> $config */
    private static function schedule(array $config): void
    {
        self::timezone($config, 'timezone');
        ConfigurationRules::integer($config, 'time_step', 1, 60);
        ConfigurationRules::integer($config, 'min_slots', 0, 100);
        ConfigurationRules::integer($config, 'max_slots', 1, 100);
        ConfigurationRules::ordered(array_replace(['min_slots' => 1, 'max_slots' => 10], $config), 'min_slots', 'max_slots');
        ConfigurationRules::choice($config, 'copy_source_day', ScheduleDays::ALL);
        foreach (['days', 'workdays', 'locked_days'] as $key) {
            ConfigurationRules::identifiers($config, $key, ScheduleDays::ALL, 7);
        }
    }

    /** @param array<string, mixed> $config */
    private static function bounds(FieldType $type, array $config): void
    {
        if ($type === FieldType::Duration) {
            $bounds = [];
            foreach (['min_value', 'max_value'] as $key) {
                if (! isset($config[$key]) || $config[$key] === '') {
                    continue;
                }
                $seconds = DurationCodec::seconds($config[$key]);
                if ($seconds === null) {
                    ConfigurationRules::fail($key, 'requires a valid duration');
                }
                $bounds[$key] = $seconds;
            }
            ConfigurationRules::ordered($bounds, 'min_value', 'max_value');

            return;
        }
        $clock = in_array($type, [FieldType::Time, FieldType::TimeRange], true);
        $defaultFormat = match ($type) {
            FieldType::Month => 'Y-m', FieldType::Year => 'Y', FieldType::DateTime => 'Y-m-d H:i:s',
            default => $clock ? 'H:i:s' : 'Y-m-d',
        };
        $storage = $config['storage_format'] ?? $defaultFormat;
        $display = $config['display_format'] ?? $defaultFormat;
        $codec = $clock ? new ClockCodec($storage, $display)
            : new CivilDateCodec($storage, $display, $config['locale'] ?? 'en', $type === FieldType::DateTime ? 'localDateTime' : 'day');
        $bounds = [];
        foreach (['min' => ['min_value', 'min_date'], 'max' => ['max_value', 'max_date']] as $bound => $keys) {
            foreach ($keys as $key) {
                if (! isset($config[$key]) || $config[$key] === '') {
                    continue;
                }
                ConfigurationRules::text($config, $key, 128);
                $canonical = $codec->canonical($config[$key]);
                if ($canonical === null) {
                    ConfigurationRules::fail($key, 'requires a real temporal value in an accepted format');
                }
                $bounds[$bound] ??= $canonical;
            }
        }
        if (isset($bounds['min'], $bounds['max']) && $bounds['min'] > $bounds['max']) {
            ConfigurationRules::fail('min_value', 'must not exceed max_value');
        }
    }
}
