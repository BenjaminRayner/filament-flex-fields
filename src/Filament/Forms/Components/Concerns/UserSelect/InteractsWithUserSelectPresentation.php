<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @phpstan-type NormalizedUserSelectOption array{
 *     value: string|int,
 *     label: string,
 *     description: ?string,
 *     icon: string|BackedEnum|Htmlable|null,
 *     image: ?string,
 *     badge: ?string,
 *     badge_color: ?string,
 *     chip_label: ?string,
 *     disabled: bool,
 *     isDisabled: bool,
 *     verified: bool,
 * }
 * @phpstan-type NormalizedSelectOption array{
 *     value: string|int,
 *     label: string,
 *     description: ?string,
 *     icon: string|BackedEnum|Htmlable|null,
 *     image: ?string,
 *     badge: ?string,
 *     badge_color: ?string,
 *     chip_label: ?string,
 *     disabled: bool,
 *     isDisabled: bool,
 * }
 *
 * @mixin UserSelect
 */
trait InteractsWithUserSelectPresentation
{
    use PresentsUserSelectChrome;
    use RendersUserSelectOptions;
    use ResolvesUserSelectDisplay;
    use ResolvesUserSelectRecords;
    use TransportsUserSelectOptions;
}
