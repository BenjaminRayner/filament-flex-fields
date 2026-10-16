<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Promotions;

use Bjanczak\FilamentFlexFields\Support\Translations;

/** Explicit local-only visual preview; never populates the publisher document or cache. */
final class SponsorPreview
{
    /** Temporary visual review on local installations; production never uses this fixture. */
    public const SHOW_ON_LOCAL_INDEX = true;

    /** @return list<array{name: string, profile: string, avatar: null, initials: string, rank: string, rankLabel: string}> */
    public static function profiles(): array
    {
        $ranks = [SponsorRank::Gold, SponsorRank::Silver, SponsorRank::Bronze];
        $perRank = (int) ceil(PromotionDocument::MAX_SPONSORS / count($ranks));

        return array_map(static function (int $position) use ($ranks, $perRank): array {
            $rank = $ranks[min(count($ranks) - 1, intdiv($position - 1, $perRank))];
            $number = str_pad((string) $position, 2, '0', STR_PAD_LEFT);

            return [
                'name' => 'Demo sponsor '.$number,
                'profile' => 'https://github.com/janczakb',
                'avatar' => null, // Do not present real people as demonstration sponsors.
                'initials' => $number,
                'rank' => $rank->value,
                'rankLabel' => Translations::get('filament-flex-fields::catalog.sponsor_ranks.'.$rank->value),
            ];
        }, range(1, PromotionDocument::MAX_SPONSORS));
    }
}
