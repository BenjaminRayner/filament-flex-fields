<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Enums\DateTimeFieldMode;
use Bjanczak\FilamentFlexFields\Enums\MonthDisplay;
use Bjanczak\FilamentFlexFields\Support\DateTime\Values\CivilDateCodec;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/** Month/year values retain their partial storage format and strict precision. */
abstract class FlexPeriodPicker extends FlexCivilDateField
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->dateEditable = false;
        $this->dateClearable = true;
        $this->showCalendar = true;
        $this->showCalendarButton = true;
        $this->closeOnSelect = true;
    }

    public function getCivilDateCodec(): CivilDateCodec
    {
        if (! in_array($this->getCalendarIdentifier(), [null, 'gregory'], true)) {
            throw new \InvalidArgumentException('Month/year values use Gregorian partial-date storage. Use DatePicker for other calendars.');
        }

        return new CivilDateCodec($this->getStorageFormat(), $this->getDisplayFormat(), $this->getLocale(), $this->getMode()->value);
    }

    public function getDisplayFormat(): string
    {
        $configured = $this->evaluate($this->displayFormat);
        if (filled($configured)) {
            return (string) $configured;
        }
        if ($this->getMode() === DateTimeFieldMode::Year) {
            return 'Y';
        }
        $month = match ($this->getMonthDisplay()) {
            MonthDisplay::Numeric => 'm',
            MonthDisplay::Short => 'M',
            MonthDisplay::Long => 'F',
        };

        return $month.($this->shouldShowYearSegment() ? ($month === 'm' ? '/Y' : ' Y') : '');
    }

    /** @param list<string> $dates
     * @return array{start: string, end: string, unavailable: list<string>, status: string}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getCivilPeriodAvailability(array $dates): array
    {
        if ($this->isDisabled() || $this->isReadOnly() || count($dates) < 1 || count($dates) > 12 || count(array_unique($dates)) !== count($dates)) {
            throw new \InvalidArgumentException('Invalid period availability window.');
        }
        $strict = new CivilDateCodec('Y-m-d', 'Y-m-d');
        $unavailable = [];
        foreach ($dates as $value) {
            $date = $strict->parse($value);
            if ($date === null || $date->format('Y-m-d') !== $value) {
                throw new \InvalidArgumentException('Invalid period availability date.');
            }
            $callback = $this->getIsDateUnavailableCallback();
            if ($callback !== null && (bool) $this->evaluate($callback, ['date' => $date->toMutable()])) {
                $unavailable[] = $value;
            }
        }
        sort($dates);

        return ['start' => $dates[0], 'end' => $dates[count($dates) - 1], 'unavailable' => $unavailable, 'status' => 'ready'];
    }
}
