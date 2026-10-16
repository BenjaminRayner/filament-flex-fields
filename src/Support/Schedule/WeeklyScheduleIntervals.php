<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schedule;

/** Half-open intervals on a cyclic week; touching endpoints do not overlap. */
final class WeeklyScheduleIntervals
{
    private const MINUTES_PER_DAY = 1440;

    private const MINUTES_PER_WEEK = 10080;

    /**
     * Input must already pass individual slot validation.
     *
     * @param  array<string, mixed>  $dayStates
     * @param  list<string>  $days
     * @return array{string, string}|null
     */
    public static function firstOverlap(array $dayStates, array $days): ?array
    {
        $segments = [];
        $normalizer = new ScheduleNormalizer;

        foreach (ScheduleDays::ALL as $index => $day) {
            if (! in_array($day, $days, true) || ! ($dayStates[$day]['enabled'] ?? false)) {
                continue;
            }

            foreach ($dayStates[$day]['slots'] ?? [] as $slot) {
                $from = $normalizer->normalizeTime($slot['from'] ?? null);
                $to = $normalizer->normalizeTime($slot['to'] ?? null);

                if ($from === null || $to === null) {
                    continue;
                }

                $start = $index * self::MINUTES_PER_DAY + self::minutes($from);
                $end = $index * self::MINUTES_PER_DAY + self::minutes($to)
                    + (($slot['overnight'] ?? false) ? self::MINUTES_PER_DAY : 0);
                $segments[] = ['start' => $start, 'end' => min($end, self::MINUTES_PER_WEEK), 'day' => $day];

                if ($end > self::MINUTES_PER_WEEK) {
                    $segments[] = ['start' => 0, 'end' => $end - self::MINUTES_PER_WEEK, 'day' => $day];
                }
            }
        }

        usort($segments, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        $previous = null;

        foreach ($segments as $segment) {
            if ($previous !== null && $segment['start'] < $previous['end']) {
                return [$previous['day'], $segment['day']];
            }

            $previous = $segment;
        }

        return null;
    }

    private static function minutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return (int) $hours * 60 + (int) $minutes;
    }
}
