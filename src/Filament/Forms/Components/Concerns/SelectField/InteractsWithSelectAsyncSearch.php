<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

/**
 * @mixin SelectField
 */
trait InteractsWithSelectAsyncSearch
{
    use ConfiguresSelectSearch;
    use InteractsWithSelectSearchCache;
    use RequestsSelectOptions;
    use RequestsSelectPages;
}
