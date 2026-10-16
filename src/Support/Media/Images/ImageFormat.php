<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images;

enum ImageFormat: string
{
    case Jpeg = 'jpg';
    case Png = 'png';
    case Webp = 'webp';
    case Avif = 'avif';

    public function mimeType(): string
    {
        return match ($this) {
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
            self::Webp => 'image/webp',
            self::Avif => 'image/avif',
        };
    }
}
