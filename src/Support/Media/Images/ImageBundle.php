<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use InvalidArgumentException;

final readonly class ImageBundle
{
    /**
     * @param  array<string, MediaReceipt>  $variants
     * @param  array<string, MediaWrite>  $intents
     */
    public function __construct(public MediaReceipt $original, public MediaWrite $intent, public array $variants, public array $intents) {}

    public function variant(string $name): MediaReceipt
    {
        return $this->variants[$name] ?? throw new InvalidArgumentException('Unknown image conversion: '.$name);
    }
}
