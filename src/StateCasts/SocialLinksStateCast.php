<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\SocialLinks\SocialLinksNormalizer;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final class SocialLinksStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return SocialLinksNormalizer::isValidShape($state) ? SocialLinksNormalizer::normalize($state) : $state;
    }

    public function set(mixed $state): mixed
    {
        return $this->get($state);
    }
}
