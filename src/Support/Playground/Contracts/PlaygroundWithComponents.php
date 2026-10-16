<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Contracts;

use Filament\Schemas\Components\Component;

interface PlaygroundWithComponents
{
    /** @return list<Component> */
    public function components(): array;
}
