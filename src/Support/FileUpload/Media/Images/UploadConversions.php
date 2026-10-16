<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Images;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFormat;

/** Compatibility options translate to named Media OS conversions; no second encoder. */
final class UploadConversions
{
    /** @return array{conversions: list<ImageConversion>, primary: string} */
    public function forFile(FlexFileUpload $field, string $mime): array
    {
        $conversions = $field->getImageConversions();
        if (! str_starts_with($mime, 'image/')) {
            return ['conversions' => [], 'primary' => 'original'];
        }
        $legacy = $field->shouldOptimizeImages() || $field->shouldOptimizeImagesToWebp()
            || $field->shouldOptimizeImagesToAvif() || $field->getFlexMaxImageWidth() !== null || $field->getFlexMaxImageHeight() !== null;
        if (! $legacy) {
            return ['conversions' => $conversions, 'primary' => 'original'];
        }
        if (! $field->shouldStripExif()) {
            throw new MediaFailure('image_metadata_policy_unsupported', 'The Media OS encoder produces metadata-free derivatives.');
        }
        foreach ($conversions as $conversion) {
            if ($conversion->name === 'optimized') {
                throw new MediaFailure('image_conversion_conflict', 'The optimized conversion name is reserved when compatibility optimization is enabled.');
            }
        }
        $format = match (true) {
            $field->shouldOptimizeImagesToAvif() => ImageFormat::Avif,
            $field->shouldOptimizeImagesToWebp() => ImageFormat::Webp,
            default => match ($mime) {
                'image/jpeg' => ImageFormat::Jpeg, 'image/png' => ImageFormat::Png,
                'image/webp' => ImageFormat::Webp, 'image/avif' => ImageFormat::Avif,
                default => throw new MediaFailure('image_type_unsupported', 'Compatibility optimization requires a supported still raster image.'),
            },
        };
        $conversions[] = new ImageConversion('optimized', width: $field->getFlexMaxImageWidth(),
            height: $field->getFlexMaxImageHeight(), format: $format, background: '#ffffff00');

        return ['conversions' => $conversions, 'primary' => 'optimized'];
    }
}
