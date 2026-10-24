<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Assets;

use Bjanczak\FilamentFlexFields\Assets\Contracts\AssetPublicationAlias;
use InvalidArgumentException;

/** Classic helper alias shares the module's exact published URL and bytes. */
final class FirstPaintScript extends FlexFieldsJs implements AssetPublicationAlias
{
    private string $chunk;

    public function chunk(string $file): static
    {
        if (preg_match('/^flex-fields-first-paint-(segment|timezone|readiness)-[a-f0-9]{64}\.js$/D', $file) !== 1) {
            throw new InvalidArgumentException('Invalid first-paint module identity.');
        }
        $this->chunk = $file;

        return $this;
    }

    public function getRelativePublicPath(): string
    {
        $immutable = $this->immutablePublicPath();
        if ($immutable !== null) {
            return $immutable;
        }
        $prefix = config('filament.assets_path', '');

        return ltrim($prefix.'/js/'.$this->getPackage().'/components/'.$this->chunk, '/');
    }

    public function getSrc(): string
    {
        return asset($this->getRelativePublicPath());
    }

    public function getPublicationOwnerId(): string
    {
        return substr($this->chunk, 0, -3);
    }
}
