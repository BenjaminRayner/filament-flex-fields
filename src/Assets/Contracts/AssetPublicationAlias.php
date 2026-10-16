<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Assets\Contracts;

/** A compatibility alias requires one registered owner at the exact same path. */
interface AssetPublicationAlias
{
    public function getPublicationOwnerId(): string;
}
