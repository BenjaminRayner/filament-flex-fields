<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Promotions;

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogBanner;
use Bjanczak\FilamentFlexFields\Support\Translations;

/** A localized first-party fallback, not a host-editable advertisement slot. */
final class OfficialPromotions
{
    /** @return list<array<string, mixed>> */
    public static function fallback(): array
    {
        return array_map(static fn (string $id, string $theme): array => (new CatalogBanner(
            id: $id, title: Translations::get('filament-flex-fields::catalog.promotions.'.$id.'.title'),
            description: Translations::get('filament-flex-fields::catalog.promotions.'.$id.'.description'),
            url: PromotionFeed::WEBSITE, action: Translations::get('filament-flex-fields::catalog.promotions.'.$id.'.action'), theme: $theme,
        ))->presentation(), ['discover-flex', 'compose-with-flex'], ['light', 'dark']);
    }

    /** @return array{arrows: bool, dots: bool, autoplay: bool, interval: int} */
    public static function sliderOptions(): array
    {
        return ['arrows' => true, 'dots' => true, 'autoplay' => true, 'interval' => 7000];
    }
}
