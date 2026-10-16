<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schedule;

use Bjanczak\FilamentFlexFields\Support\Translations;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Read-only projection of the same state stored by ScheduleField. */
final readonly class ScheduleDisplay
{
    /** @param list<string> $days */
    public function __construct(public array $days = ScheduleDays::ALL, public string $locale = 'en', public int $hourCycle = 24, public bool $showBreaks = true)
    {
        if (! in_array($hourCycle, [12, 24], true)) {
            throw new InvalidArgumentException('Schedule hour cycle must be 12 or 24.');
        }
    }

    /** @return array{timezone: ?string, active: bool, closed: string, days: list<array{key: string, label: string, short: string, active: bool, hours: string, tooltip: string}>} */
    public function project(mixed $state): array
    {
        if (is_string($state)) {
            $state = json_decode($state, true);
        }
        $schedule = (new ScheduleNormalizer)->normalize($state, $this->days);
        $closed = Translations::get('filament-flex-fields::default.schedule.closed', locale: $this->locale);
        $rows = [];
        $hasActiveDay = false;
        foreach ($schedule['days'] as $key => $day) {
            $date = (new CarbonImmutable('2026-06-01', 'UTC'))->addDays((int) array_search($key, ScheduleDays::ALL, true));
            $date->locale($this->locale);
            $label = $date->translatedFormat('l');
            $hours = [];
            $work = [];
            foreach ($day['slots'] as $slot) {
                $isBreak = ($slot['type'] ?? 'slot') === 'break';
                $text = $this->time($slot['from']).'–'.$this->time($slot['to']);
                if ($slot['overnight'] ?? false) {
                    $text .= ' (+1)';
                }
                if (! $isBreak) {
                    $work[] = $text;
                }
                if (! $isBreak || $this->showBreaks) {
                    $hours[] = $isBreak ? Translations::get('filament-flex-fields::default.schedule.break', locale: $this->locale).': '.$text : $text;
                }
            }
            $active = $day['enabled'] && $work !== [];
            $hasActiveDay = $hasActiveDay || $active;
            $summary = $active ? implode(', ', $hours) : $closed;
            $rows[] = ['key' => $key, 'label' => $label, 'short' => $date->translatedFormat('D'), 'active' => $active, 'hours' => $summary, 'tooltip' => $label.': '.$summary];
        }

        return ['timezone' => $schedule['timezone'] ?? null, 'active' => $hasActiveDay, 'closed' => $closed, 'days' => $rows];
    }

    private function time(string $time): string
    {
        return $this->hourCycle === 24 ? $time : (CarbonImmutable::createFromFormat('!H:i', $time, 'UTC')?->format('g:i A') ?? $time);
    }
}
