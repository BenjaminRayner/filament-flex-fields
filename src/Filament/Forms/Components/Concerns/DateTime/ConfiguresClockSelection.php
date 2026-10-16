<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DateTime;

use Closure;
use InvalidArgumentException;

trait ConfiguresClockSelection
{
    protected int|Closure $calendarMinuteStep = 15;

    protected int|Closure $calendarSecondStep = 1;

    protected string|Closure $calendarDefaultTime = '00:00:00';

    /** @var list<string>|Closure|null */
    protected array|Closure|null $allowedTimes = null;

    protected bool $hasExplicitDefaultTime = false;

    /**
     * Null keeps the interval list; an empty list permits no time.
     *
     * @param  list<string>|Closure|null  $times
     */
    public function allowedTimes(array|Closure|null $times): static
    {
        $this->allowedTimes = $times;

        return $this;
    }

    /** @return list<string>|null */
    public function getAllowedTimes(): ?array
    {
        $times = $this->evaluate($this->allowedTimes);
        if ($times === null) {
            return null;
        }
        if (! is_array($times) || count($times) > 1440) {
            throw new InvalidArgumentException('Allowed times must be a list containing at most 1440 HH:mm[:ss] values.');
        }
        $canonical = [];
        foreach ($times as $time) {
            if (! is_string($time) || preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $time) !== 1) {
                throw new InvalidArgumentException('Allowed times must use HH:mm or HH:mm:ss.');
            }
            $time = strlen($time) === 5 ? $time.':00' : $time;
            if (! str_ends_with($time, ':00') && ! str_contains($this->getStorageFormat(), 's')) {
                throw new InvalidArgumentException('Allowed times with seconds require storage with second precision.');
            }
            $canonical[] = $time;
        }
        $canonical = array_values(array_unique($canonical));
        sort($canonical, SORT_STRING);

        return $canonical;
    }

    public function minuteStep(int|Closure $step): static
    {
        $this->calendarMinuteStep = $step;

        return $this;
    }

    public function secondStep(int|Closure $step): static
    {
        $this->calendarSecondStep = $step;

        return $this;
    }

    public function defaultTime(string|Closure $time): static
    {
        $this->calendarDefaultTime = $time;
        $this->hasExplicitDefaultTime = true;

        return $this;
    }

    public function getCalendarMinuteStep(): int
    {
        $step = $this->evaluate($this->calendarMinuteStep);
        if (! is_int($step) || $step < 1 || $step > 60 || 60 % $step !== 0) {
            throw new InvalidArgumentException('Calendar minute step must divide 60.');
        }

        return $step;
    }

    public function getCalendarSecondStep(): int
    {
        $step = $this->evaluate($this->calendarSecondStep);
        if (! is_int($step) || $step < 1 || $step > 60 || 60 % $step !== 0) {
            throw new InvalidArgumentException('Calendar second step must divide 60.');
        }

        return $step;
    }

    public function getCalendarDefaultTime(): string
    {
        $time = $this->evaluate($this->calendarDefaultTime);
        if (! is_string($time) || preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $time) !== 1) {
            throw new InvalidArgumentException('Default calendar time must be HH:mm or HH:mm:ss.');
        }

        $time = strlen($time) === 5 ? $time.':00' : $time;
        $allowed = $this->getAllowedTimes();
        if ($allowed !== null && ! in_array($time, $allowed, true)) {
            if ($this->hasExplicitDefaultTime) {
                throw new InvalidArgumentException('Default time must be one of the allowed times.');
            }

            return $allowed[0] ?? $time;
        }

        return $time;
    }
}
