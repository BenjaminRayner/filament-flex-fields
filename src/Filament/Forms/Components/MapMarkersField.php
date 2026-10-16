<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

/** Domain defaults composed over the single map renderer and runtime. */
class MapMarkersField extends MapPickerField
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->multipleMarkers();
    }

    public function getLocationComponentId(): string
    {
        return 'map-markers';
    }
}
