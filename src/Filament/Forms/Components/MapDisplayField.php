<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

/** Domain defaults composed over the single map renderer and runtime. */
class MapDisplayField extends MapPickerField
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->readOnly()->searchable(false);
    }

    public function getLocationComponentId(): string
    {
        return 'map-display';
    }
}
