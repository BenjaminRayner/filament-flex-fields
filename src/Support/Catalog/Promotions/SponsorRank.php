<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Promotions;

enum SponsorRank: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';

    public function priority(): int
    {
        return match ($this) {
            self::Gold => 0, self::Silver => 1, self::Bronze => 2
        };
    }
}
