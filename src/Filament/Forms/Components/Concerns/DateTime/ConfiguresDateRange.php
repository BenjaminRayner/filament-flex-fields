<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DateTime;

use Bjanczak\FilamentFlexFields\Support\DateTime\Values\CivilDateCodec;
use Closure;
use InvalidArgumentException;

trait ConfiguresDateRange
{
    /** @var list<array{label: string, start: string, end: string}>|Closure|null */
    protected array|Closure|null $dateRangePresets = null;

    protected int|string|Closure $calendarMonthCount = 'auto';

    /** @param list<array{label: string, start: string, end: string}>|Closure|null $presets */
    public function rangePresets(array|Closure|null $presets): static
    {
        $this->dateRangePresets = $presets;

        return $this;
    }

    public function calendarMonths(int|string|Closure $count): static
    {
        $this->calendarMonthCount = $count;

        return $this;
    }

    public function getCalendarMonths(): int|string
    {
        $count = $this->evaluate($this->calendarMonthCount);
        if (! in_array($count, [1, 2, 'auto'], true)) {
            throw new InvalidArgumentException('Calendar months must be 1, 2 or auto.');
        }

        return $count;
    }

    /** @return list<array{label: string, start: string, end: string}>|null */
    public function getRangePresets(): ?array
    {
        $presets = $this->evaluate($this->dateRangePresets);
        if ($presets === null) {
            return null;
        }
        if (! is_array($presets) || count($presets) > 30) {
            throw new InvalidArgumentException('Range presets must be a list of at most 30 ranges.');
        }
        $codec = new CivilDateCodec('Y-m-d', 'Y-m-d');
        $result = [];
        foreach ($presets as $preset) {
            if (! is_array($preset) || ! is_string($preset['label'] ?? null) || trim($preset['label']) === '') {
                throw new InvalidArgumentException('Every range preset requires a nonempty label.');
            }
            $start = $codec->canonical($preset['start'] ?? null);
            $end = $codec->canonical($preset['end'] ?? null);
            if ($start === null || $end === null || $start > $end) {
                throw new InvalidArgumentException('Range preset dates must form a valid ordered range.');
            }
            $result[] = ['label' => $preset['label'], 'start' => $start, 'end' => $end];
        }

        return $result;
    }
}
