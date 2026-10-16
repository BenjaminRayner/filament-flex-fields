<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schedule;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use InvalidArgumentException;

/** Evaluated configuration has explicit bounds before reaching HTML or the command engine. */
final class SchedulePolicy
{
    public const MAX_SLOTS = 100;

    /** @return list<string> */
    public static function days(mixed $value, bool $fallbackWeekdays = false, bool $allowEmpty = false): array
    {
        if (! is_array($value) || count($value) > 100) {
            throw new InvalidArgumentException('Schedule days must be a bounded array.');
        }
        $days = [];
        foreach ($value as $day) {
            $day = strtolower(trim(PickerConfiguration::text($day, 'schedule day')));
            if (in_array($day, ScheduleDays::ALL, true)) {
                $days[$day] = $day;
            }
        }

        return $days !== [] || $allowEmpty ? array_values($days) : ($fallbackWeekdays ? ScheduleDays::WEEKDAYS : ScheduleDays::ALL);
    }

    public static function slots(mixed $value, string $name, int $minimum): int
    {
        $count = PickerConfiguration::integer($value, $name);
        if ($count < $minimum || $count > self::MAX_SLOTS) {
            throw new InvalidArgumentException("Schedule {$name} must be {$minimum}–100.");
        }

        return $count;
    }

    public static function flag(mixed $value, string $name): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException("Schedule {$name} must resolve to boolean.");
        }

        return $value;
    }

    /** @return list<array{value: string, label: string}> */
    public static function times(int $step): array
    {
        if ($step < 1 || $step > 60) {
            throw new InvalidArgumentException('Schedule time grid must be 1–60 minutes.');
        }
        $options = [];
        for ($minute = 0; $minute < 1440; $minute += $step) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            $options[] = ['value' => $time, 'label' => $time];
        }

        return $options;
    }
}
