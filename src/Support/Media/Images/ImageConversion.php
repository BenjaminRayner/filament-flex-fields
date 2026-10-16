<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images;

use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use InvalidArgumentException;

/** A named derivative, usable by fields, jobs and third-party component authors. */
final readonly class ImageConversion
{
    public function __construct(
        public string $name,
        public ?int $width = null,
        public ?int $height = null,
        public ImageFit $fit = ImageFit::Contain,
        public ImageFormat $format = ImageFormat::Webp,
        public int $quality = 82,
        public ?string $disk = null,
        public ?string $directory = null,
        public bool $upscale = false,
        public float $focusX = 0.5,
        public float $focusY = 0.5,
        public string $background = '#ffffff',
        public bool $autoOrient = true,
        public bool $firstFrame = false,
        public bool $progressive = true,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $name) || $name === 'original') {
            throw new InvalidArgumentException('Invalid image conversion name.');
        }
        if (($width !== null && ($width < 1 || $width > 32768)) || ($height !== null && ($height < 1 || $height > 32768))) {
            throw new InvalidArgumentException('Image dimensions must be between 1 and 32768.');
        }
        if ($fit !== ImageFit::Contain && ($width === null || $height === null)) {
            throw new InvalidArgumentException('Cover, pad and stretch need both dimensions.');
        }
        if ($quality < 1 || $quality > 100 || ! is_finite($focusX) || ! is_finite($focusY)
            || $focusX < 0 || $focusX > 1 || $focusY < 0 || $focusY > 1) {
            throw new InvalidArgumentException('Invalid image quality or focal point.');
        }
        if (! preg_match('/^#[a-f0-9]{6}(?:[a-f0-9]{2})?$/iD', $background)) {
            throw new InvalidArgumentException('Image background must be a hex RGB or RGBA color.');
        }
        if ($disk !== null && ($disk === '' || strlen($disk) > 255 || preg_match('/[\x00-\x1F\x7F]/', $disk))) {
            throw new InvalidArgumentException('Invalid image conversion disk.');
        }
        if ($directory !== null) {
            MediaAddress::path($directory, allowEmpty: true);
        }
    }
}
