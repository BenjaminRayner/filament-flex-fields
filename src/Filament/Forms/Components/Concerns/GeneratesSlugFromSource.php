<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug\HasGenerationOptions;

/** @deprecated Use Concerns\Slug\HasGenerationOptions. */
trait GeneratesSlugFromSource
{
    use HasGenerationOptions;
}
