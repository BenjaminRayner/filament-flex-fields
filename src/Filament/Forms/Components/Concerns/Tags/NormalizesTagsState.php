<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Tags;

use Bjanczak\FilamentFlexFields\Support\Tags\TagState;

trait NormalizesTagsState
{
    /** @return list<string> */
    public function normalizeTagsState(mixed $state): array
    {
        return TagState::normalize($state, $this->getSeparator());
    }
}
