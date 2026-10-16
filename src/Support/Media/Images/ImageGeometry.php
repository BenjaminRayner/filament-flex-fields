<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images;

/** Integer sampling rectangles avoid CSS/browser-dependent crop semantics. */
final readonly class ImageGeometry
{
    public function __construct(
        public int $width, public int $height,
        public int $sourceX, public int $sourceY, public int $sourceWidth, public int $sourceHeight,
        public int $targetX, public int $targetY, public int $targetWidth, public int $targetHeight,
    ) {}

    public static function for(int $width, int $height, ImageConversion $conversion): self
    {
        $targetWidth = $conversion->width ?? ($conversion->height === null ? $width : max(1, (int) round($width * $conversion->height / $height)));
        $targetHeight = $conversion->height ?? max(1, (int) round($height * $targetWidth / $width));
        if (! $conversion->upscale && $conversion->fit === ImageFit::Cover) {
            $scale = min(1, $width / $targetWidth, $height / $targetHeight);
            $targetWidth = max(1, (int) floor($targetWidth * $scale));
            $targetHeight = max(1, (int) floor($targetHeight * $scale));
        }
        if ($conversion->fit === ImageFit::Cover) {
            $scale = max($targetWidth / $width, $targetHeight / $height);
            $cropWidth = min($width, max(1, (int) round($targetWidth / $scale)));
            $cropHeight = min($height, max(1, (int) round($targetHeight / $scale)));

            return new self($targetWidth, $targetHeight,
                (int) round(($width - $cropWidth) * $conversion->focusX), (int) round(($height - $cropHeight) * $conversion->focusY),
                $cropWidth, $cropHeight, 0, 0, $targetWidth, $targetHeight);
        }
        if ($conversion->fit === ImageFit::Stretch) {
            if (! $conversion->upscale) {
                $targetWidth = min($width, $targetWidth);
                $targetHeight = min($height, $targetHeight);
            }

            return new self($targetWidth, $targetHeight, 0, 0, $width, $height, 0, 0, $targetWidth, $targetHeight);
        }
        $scale = min($targetWidth / $width, $targetHeight / $height, $conversion->upscale ? PHP_FLOAT_MAX : 1);
        $scaledWidth = max(1, (int) round($width * $scale));
        $scaledHeight = max(1, (int) round($height * $scale));
        if ($conversion->fit === ImageFit::Contain) {
            return new self($scaledWidth, $scaledHeight, 0, 0, $width, $height, 0, 0, $scaledWidth, $scaledHeight);
        }

        return new self($targetWidth, $targetHeight, 0, 0, $width, $height,
            (int) floor(($targetWidth - $scaledWidth) / 2), (int) floor(($targetHeight - $scaledHeight) / 2), $scaledWidth, $scaledHeight);
    }
}
