<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Schedule\ConfiguresSchedule;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Schedule\PresentsSchedule;
use Bjanczak\FilamentFlexFields\Support\DateTime\DateTimeOs;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleNormalizer;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleValidator;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;

/** Native Field lifecycle around the weekly domain; embedded controls own their Select runtime. */
class ScheduleField extends Field
{
    use CanBeReadOnly;
    use ConfiguresSchedule;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use PresentsSchedule;

    protected string $view = 'filament-flex-fields::forms.components.schedule-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->size('sm');
        $this->default(fn (self $component): array => self::defaultSchedule($component->getDefaultTimezoneIdentifier(), $component->getDays()));
        $this->afterStateHydrated(function (self $component, mixed $state): void {
            $normalized = $component->normalizeState($state);
            if (! $component->stateMatches($normalized, $state)) {
                $component->state($normalized);
            }
        });
        $this->dehydrateStateUsing(fn (self $component, mixed $state): array => $component->normalizeState($state));
        $this->rule(fn (self $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            if ($component->isRequired() && $component->isEmptyState($value)) {
                $fail(__('validation.required', ['attribute' => $component->getLabel()]));

                return;
            }
            if ($value === null || $value === []) {
                $component->validateLockedDays($value, $fail);

                return;
            }
            $valid = true;
            $component->makeValidator()->validate($value, $component->getDays(), function (string $message) use ($fail, &$valid): void {
                $valid = false;
                $fail($message);
            }, $component->getDefaultTimezoneIdentifier(),
                $component->showsTimezoneSelector() ? $component->getResolvedTimezoneIdentifiers() : null);
            if ($valid) {
                $component->validateLockedDays($value, $fail);
            }
        });
    }

    public function getRequiredValidationRule(): string|Closure
    {
        return $this->isRequired() ? 'required' : 'nullable';
    }

    /**
     * @param  list<string>|null  $days
     * @return array<string, mixed>
     */
    public static function defaultSchedule(?string $timezone = null, ?array $days = null): array
    {
        return DateTimeOs::businessHoursDefaults($timezone, $days);
    }

    /** @return array<string,mixed> */
    public function normalizeState(mixed $state): array
    {
        $timezone = $this->getDefaultTimezoneIdentifier();
        $normalized = $this->makeNormalizer()->normalize($state, $this->getDays(), $timezone);
        if ($timezone === null) {
            unset($normalized['timezone']);
        }

        return $normalized;
    }

    public function isEmptyState(mixed $state): bool
    {
        if (! is_array($state)) {
            return true;
        }
        $days = is_array($state['days'] ?? null) ? $state['days'] : [];
        foreach ($this->getDays() as $name) {
            $day = $days[$name] ?? null;
            if (! is_array($day) || ! in_array($day['enabled'] ?? false, [true, 1, '1'], true) || ! is_array($day['slots'] ?? null)) {
                continue;
            }
            // Required checking must not parse unbounded input before the validator's limits.
            if (count($day['slots']) > $this->getMaxSlots()) {
                return false;
            }
            foreach ($day['slots'] as $slot) {
                if (is_array($slot) && ($slot['type'] ?? 'slot') === 'slot') {
                    return false;
                }
            }
        }

        return true;
    }

    /** Compare actual hydrated data, not the result of normalizing both sides. */
    /** @param array<string, mixed> $normalized */
    public function stateMatches(array $normalized, mixed $state): bool
    {
        return $normalized === $state;
    }

    protected function makeNormalizer(): ScheduleNormalizer
    {
        return new ScheduleNormalizer;
    }

    protected function makeValidator(): ScheduleValidator
    {
        return new ScheduleValidator($this->getMinSlots(), $this->getMaxSlots(), $this->shouldRequireSlotsForEnabledDays());
    }

    /** lockedDays protects the enabled flag; slot editing keeps its existing public contract. */
    private function validateLockedDays(mixed $value, Closure $fail): void
    {
        $days = $this->getLockedDays();
        if ($days === []) {
            return;
        }
        $baseline = $this->getLockedSchedule();
        $submitted = $this->normalizeState($value);
        foreach ($days as $day) {
            if ($baseline['days'][$day]['enabled'] !== $submitted['days'][$day]['enabled']) {
                $fail(__('validation.prohibited', ['attribute' => __("filament-flex-fields::default.schedule.days.{$day}")]));

                return;
            }
        }
    }
}
