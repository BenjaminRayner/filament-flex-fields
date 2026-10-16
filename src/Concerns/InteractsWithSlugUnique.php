<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug\ValidatesSlug;

/** @deprecated Use the Slug\ValidatesSlug concern. */
trait InteractsWithSlugUnique
{
    use ValidatesSlug;
}
