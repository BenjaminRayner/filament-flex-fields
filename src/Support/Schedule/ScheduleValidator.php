<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schedule;

use Bjanczak\FilamentFlexFields\Support\DateTime\ScheduleV2;
use Closure;
use DateTimeZone;

final class ScheduleValidator
{
    public function __construct(
        private readonly int $minSlots = 1,
        private readonly int $maxSlots = 10,
        private readonly bool $requireSlotsForEnabledDays = true,
    ) {}

    /**
     * @param  list<string>  $days
     * @param  list<string>|null  $allowedTimezones
     */
    public function validate(mixed $state, array $days, Closure $fail, ?string $timezone = null, ?array $allowedTimezones = null): void
    {
        if (! is_array($state)) {
            $fail(__('filament-flex-fields::default.schedule.validation.invalid'));

            return;
        }

        $days = ScheduleDays::normalize($days);
        $dayStates = is_array($state['days'] ?? null) ? $state['days'] : [];

        if ($timezone !== null) {
            $submittedTimezone = $state['timezone'] ?? null;

            if (! is_string($submittedTimezone) || trim($submittedTimezone) === '') {
                $fail(__('filament-flex-fields::default.schedule.validation.timezone_required'));

                return;
            }

            if (! in_array(trim($submittedTimezone), DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)
                || (is_array($allowedTimezones) && ! in_array(trim($submittedTimezone), $allowedTimezones, true))) {
                $fail(__('validation.in', ['attribute' => __('filament-flex-fields::default.schedule.timezone')]));

                return;
            }
        }

        foreach ($days as $day) {
            $dayState = is_array($dayStates[$day] ?? null) ? $dayStates[$day] : null;

            if ($dayState === null) {
                $fail(__('filament-flex-fields::default.schedule.validation.missing_day', [
                    'day' => __("filament-flex-fields::default.schedule.days.{$day}"),
                ]));

                return;
            }

            $valid = true;
            $this->validateDay($day, $dayState, function (string $message) use ($fail, &$valid): void {
                $valid = false;
                $fail($message);
            });

            if (! $valid) {
                return;
            }
        }

        $overlap = WeeklyScheduleIntervals::firstOverlap($dayStates, $days);

        if ($overlap !== null) {
            $fail(__('filament-flex-fields::default.schedule.validation.overlap', [
                'day' => implode(' / ', array_map(static function (string $day): string {
                    $label = __("filament-flex-fields::default.schedule.days.{$day}");

                    return is_string($label) ? $label : $day;
                }, $overlap)),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $dayState
     */
    public function validateDay(string $day, array $dayState, Closure $fail): void
    {
        if (! in_array($dayState['enabled'] ?? false, [true, false, 0, 1, '0', '1'], true)
            || (array_key_exists('slots', $dayState) && (! is_array($dayState['slots']) || ! array_is_list($dayState['slots'])))) {
            $fail(__('filament-flex-fields::default.schedule.validation.invalid'));

            return;
        }
        $enabled = (bool) ($dayState['enabled'] ?? false);
        $slots = is_array($dayState['slots'] ?? null) ? $dayState['slots'] : [];
        $dayLabel = __("filament-flex-fields::default.schedule.days.{$day}");
        // Bound work before parsing even disabled days; retained slots are persisted too.
        if (count($slots) > $this->maxSlots) {
            $fail(__('filament-flex-fields::default.schedule.validation.max_slots', [
                'day' => $dayLabel, 'count' => $this->maxSlots,
            ]));

            return;
        }

        $normalizedSlots = [];

        foreach ($slots as $index => $slot) {
            if (! is_array($slot)
                || ! in_array($slot['overnight'] ?? false, [true, false, 0, 1, '0', '1'], true)
                || ! in_array($slot['type'] ?? 'slot', ['slot', 'break'], true)) {
                $fail(__('filament-flex-fields::default.schedule.validation.invalid_slot', [
                    'day' => $dayLabel,
                    'slot' => $index + 1,
                ]));

                return;
            }

            $from = $this->submittedTime($slot['from'] ?? null);
            $to = $this->submittedTime($slot['to'] ?? null);

            if ($from === null || $to === null) {
                $fail(__('filament-flex-fields::default.schedule.validation.invalid_time', [
                    'day' => $dayLabel,
                    'slot' => $index + 1,
                ]));

                return;
            }

            $overnight = ScheduleV2::slotIsOvernight($slot);

            if ($enabled && ((! $overnight && $this->timeToMinutes($from) >= $this->timeToMinutes($to))
                || ($overnight && $this->timeToMinutes($from) < $this->timeToMinutes($to)))) {
                $fail(__('filament-flex-fields::default.schedule.validation.from_before_to', [
                    'day' => $dayLabel,
                    'slot' => $index + 1,
                ]));

                return;
            }

            $normalizedSlots[] = [
                'from' => $from,
                'to' => $to,
                'overnight' => $overnight,
            ];
        }

        if (! $enabled) {
            return;
        }

        if ($this->requireSlotsForEnabledDays && count($normalizedSlots) < $this->minSlots) {
            $fail(__('filament-flex-fields::default.schedule.validation.min_slots', [
                'day' => $dayLabel,
                'count' => $this->minSlots,
            ]));

            return;
        }

        if ($this->slotsOverlap($normalizedSlots)) {
            $fail(__('filament-flex-fields::default.schedule.validation.overlap', [
                'day' => $dayLabel,
            ]));
        }
    }

    private function submittedTime(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 16
            || preg_match('/^(\d{1,2}):(\d{2})$/D', trim($value), $match) !== 1
            || (int) $match[1] > 23 || (int) $match[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $match[1], (int) $match[2]);
    }

    /**
     * @param  list<array{from: string, to: string, overnight?: bool}>  $slots
     */
    public function slotsOverlap(array $slots): bool
    {
        return ! ScheduleV2::validateNoOverlap($slots);
    }

    public function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
