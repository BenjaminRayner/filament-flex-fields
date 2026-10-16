<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use InvalidArgumentException;

/** Bounds are checked before GD allocates decoded buffers. */
final readonly class ImageLimits
{
    public function __construct(
        public int $sourcePixels = 16000000,
        public int $outputPixels = 16000000,
        public int $variants = 16,
        public int $workingBytes = 268435456,
    ) {
        if ($sourcePixels < 1 || $sourcePixels > 100000000 || $outputPixels < 1 || $outputPixels > 100000000
            || $variants < 1 || $variants > 64 || $workingBytes < 1048576) {
            throw new InvalidArgumentException('Invalid image conversion budgets.');
        }
    }

    public static function fromConfig(): self
    {
        $values = [];
        foreach (['source_pixels' => 16000000, 'output_pixels' => 16000000, 'variants' => 16, 'working_bytes' => 268435456] as $key => $default) {
            $value = filter_var(config('filament-flex-fields.media_capture.images.'.$key, $default), FILTER_VALIDATE_INT);
            if ($value === false) {
                throw new InvalidArgumentException('Invalid image conversion configuration: '.$key);
            }
            $values[$key] = $value;
        }

        return new self($values['source_pixels'], $values['output_pixels'], $values['variants'], $values['working_bytes']);
    }

    public function check(int $width, int $height, int $outputWidth, int $outputHeight): void
    {
        $source = $width * $height;
        $output = $outputWidth * $outputHeight;
        if ($width < 1 || $height < 1 || $outputWidth < 1 || $outputHeight < 1
            || $source > $this->sourcePixels || $output > $this->outputPixels) {
            throw new MediaFailure('image_pixel_limit', 'Image dimensions exceed the conversion budget.');
        }
        // Source, orientation replacement and canvas can coexist; leave encoder overhead.
        $estimate = $source * 12 + $output * 8 + 8388608;
        $configured = ini_get('memory_limit');
        $available = $configured === false || $configured === '-1'
            ? PHP_INT_MAX : ini_parse_quantity($configured) - memory_get_usage(true);
        if ($estimate > $this->workingBytes || $estimate > $available) {
            throw new MediaFailure('image_memory_limit', 'Image conversion cannot fit its working memory budget.');
        }
    }
}
