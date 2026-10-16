<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Assets\Concerns;

use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use RuntimeException;

/** Filament retains logical registration; FLEX alone commits sealed local files. */
trait ResolvesImmutableAssetRelease
{
    protected function immutablePublicPath(): ?string
    {
        $prefix = config('filament.assets_path') ?? '';
        if (! is_string($prefix)) {
            throw new RuntimeException('Filament asset prefix must be a string.');
        }

        return RequestState::resolve(BuiltInAssetRelease::class)->publicPath($this->getPath(), $prefix);
    }

    public function getRelativePublicPath(): string
    {
        return $this->immutablePublicPath() ?? parent::getRelativePublicPath();
    }

    public function isRemote(): bool
    {
        // Filament's native copier skips remote-managed assets. Render methods
        // below still resolve these local files through the immutable publisher.
        return parent::isRemote() || $this->immutablePublicPath() !== null;
    }
}
