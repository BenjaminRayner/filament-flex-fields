<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;

interface ChildFactory
{
    public function make(ChildRenderContext $context): Component|Column|Action;
}
