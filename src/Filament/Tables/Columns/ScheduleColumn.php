<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Tables\Columns;

use Bjanczak\FilamentFlexFields\Filament\Concerns\ConfiguresScheduleDisplay;
use Bjanczak\FilamentFlexFields\Filament\Concerns\EmitsFlexDisplayAssets;
use Filament\Tables\Columns\Column;

class ScheduleColumn extends Column
{
    use ConfiguresScheduleDisplay;
    use EmitsFlexDisplayAssets;

    protected string $view = 'filament-flex-fields::tables.columns.schedule-column';

    protected function flexDisplayComponentId(): string
    {
        return 'schedule-column';
    }
}
