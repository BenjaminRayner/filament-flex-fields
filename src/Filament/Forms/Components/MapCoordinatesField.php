<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

/** Domain defaults composed over the single map renderer and runtime. */
class MapCoordinatesField extends MapPickerField
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->reverseGeocoding(false)->searchable(false)->manualCoordinates()->fields(['lat', 'lng'])->requiredFields(['lat', 'lng']);
    }

    public function getLocationComponentId(): string
    {
        return 'map-coordinates';
    }
}
