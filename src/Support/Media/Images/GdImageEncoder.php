<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use GdImage;

/** Optional GD implementation. No image extension is loaded during provider boot. */
final class GdImageEncoder implements ImageEncoder
{
    public function encode(PreparedMedia $original, ImageConversion $conversion, ImageLimits $limits, MediaPolicy $policy): PreparedMedia
    {
        $encode = match ($conversion->format) {
            ImageFormat::Jpeg => 'imagejpeg', ImageFormat::Png => 'imagepng',
            ImageFormat::Webp => 'imagewebp', ImageFormat::Avif => 'imageavif',
        };
        if (! function_exists('imagecreatetruecolor') || ! function_exists($encode)) {
            throw new MediaFailure('image_encoder_unavailable', 'The requested image encoder is unavailable.');
        }
        $size = @getimagesize($original->path);
        if ($size === false || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF], true)) {
            throw new MediaFailure('image_type_unsupported', 'Only supported raster images can be converted.');
        }
        if (! $conversion->firstFrame && $this->mayBeAnimated($original->path, $size[2])) {
            throw new MediaFailure('image_animation_rejected', 'Flattening an animated image requires firstFrame.');
        }
        $orientation = 1;
        if ($conversion->autoOrient && $size[2] === IMAGETYPE_JPEG) {
            if (! function_exists('exif_read_data')) {
                throw new MediaFailure('image_orientation_unavailable', 'JPEG auto orientation requires the EXIF extension.');
            }
            $exif = @exif_read_data($original->path, 'IFD0');
            $orientation = is_array($exif) && is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
        }
        [$width, $height] = in_array($orientation, [5, 6, 7, 8], true) ? [$size[1], $size[0]] : [$size[0], $size[1]];
        $geometry = ImageGeometry::for($width, $height, $conversion);
        $limits->check($width, $height, $geometry->width, $geometry->height);
        $decode = match ($size[2]) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_GIF => 'imagecreatefromgif', IMAGETYPE_WEBP => 'imagecreatefromwebp',
            IMAGETYPE_AVIF => 'imagecreatefromavif',
        };
        if (! function_exists($decode)) {
            throw new MediaFailure('image_decoder_unavailable', 'The source image decoder is unavailable.');
        }
        $image = @$decode($original->path);
        if (! $image instanceof GdImage) {
            throw new MediaFailure('image_decode_failed', 'Unable to decode the source image.');
        }
        $canvas = null;
        $output = null;
        try {
            $image = $this->orient($image, $orientation);
            if ($geometry->width < 1 || $geometry->height < 1) {
                throw new MediaFailure('image_pixel_limit', 'Image canvas must have positive dimensions.');
            }
            $canvas = imagecreatetruecolor($geometry->width, $geometry->height);
            if (! $canvas instanceof GdImage) {
                throw new MediaFailure('image_allocation_failed', 'Unable to allocate the image canvas.');
            }
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $color = substr($conversion->background, 1);
            $alpha = strlen($color) === 8 ? (int) round((255 - hexdec(substr($color, 6, 2))) * 127 / 255) : 0;
            if ($conversion->format === ImageFormat::Jpeg) {
                $alpha = 0;
            }
            $background = imagecolorallocatealpha($canvas, $this->channel(substr($color, 0, 2)),
                $this->channel(substr($color, 2, 2)), $this->channel(substr($color, 4, 2)), min(127, max(0, $alpha)));
            if ($background === false || ! imagefill($canvas, 0, 0, $background)) {
                throw new MediaFailure('image_render_failed', 'Unable to fill the image canvas.');
            }
            imagealphablending($canvas, true);
            if (! imagecopyresampled($canvas, $image, $geometry->targetX, $geometry->targetY,
                $geometry->sourceX, $geometry->sourceY, $geometry->targetWidth, $geometry->targetHeight,
                $geometry->sourceWidth, $geometry->sourceHeight)) {
                throw new MediaFailure('image_render_failed', 'Unable to sample the image.');
            }
            imageinterlace($canvas, $conversion->progressive);
            $output = tmpfile();
            if (! is_resource($output)) {
                throw new MediaFailure('image_encode_failed', 'Unable to allocate the encoded image.');
            }
            $outputPath = stream_get_meta_data($output)['uri'] ?? null;
            if (! is_string($outputPath) || $outputPath === '') {
                throw new MediaFailure('image_encode_failed', 'Encoded image has no readable path.');
            }
            $quality = $conversion->format === ImageFormat::Png ? (int) round((100 - $conversion->quality) * 9 / 99) : $conversion->quality;
            if (! @$encode($canvas, $outputPath, $quality) || filesize($outputPath) === 0) {
                throw new MediaFailure('image_encode_failed', 'Unable to encode the requested format.');
            }
            $prepared = PreparedMedia::prepare(MediaSource::path($outputPath, $conversion->name.'.'.$conversion->format->value), $policy);
            if ($prepared->file->mimeType !== $conversion->format->mimeType()) {
                $prepared->close();
                throw new MediaFailure('image_encode_failed', 'Image encoder returned an unexpected format.');
            }

            return $prepared;
        } finally {
            // PHP 8 GD objects release their buffers when references leave this scope.
            unset($image, $canvas);
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    /** @return int<0, 255> */
    private function channel(string $hex): int
    {
        return min(255, max(0, (int) hexdec($hex)));
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 5, 7], true) && ! imageflip($image, IMG_FLIP_HORIZONTAL)) {
            throw new MediaFailure('image_orientation_failed', 'Unable to reflect image orientation.');
        }
        if ($orientation === 4 && ! imageflip($image, IMG_FLIP_VERTICAL)) {
            throw new MediaFailure('image_orientation_failed', 'Unable to reflect image orientation.');
        }
        $angle = match ($orientation) {
            3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if (! $rotated instanceof GdImage) {
            throw new MediaFailure('image_orientation_failed', 'Unable to rotate image orientation.');
        }

        return $rotated;
    }

    private function mayBeAnimated(string $path, int $type): bool
    {
        if ($type === IMAGETYPE_GIF || $type === IMAGETYPE_AVIF) {
            // Conservative: GD flattens GIF and AVIF; opt in rather than lose animation silently.
            return true;
        }
        if (! in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return false;
        }
        $stream = fopen($path, 'rb');
        if (! is_resource($stream)) {
            throw new MediaFailure('image_read_failed', 'Unable to inspect image animation.');
        }
        try {
            $webp = $type === IMAGETYPE_WEBP;
            fseek($stream, $webp ? 12 : 8);
            $chunks = 0;
            $fileBytes = filesize($path);
            if ($fileBytes === false) {
                throw new MediaFailure('image_read_failed', 'Unable to measure image chunks.');
            }
            while (! feof($stream)) {
                $header = fread($stream, 8);
                if ($header === '' || $header === false) {
                    break;
                }
                if (strlen($header) !== 8) {
                    throw new MediaFailure('image_decode_failed', 'Truncated image chunk.');
                }
                $name = $webp ? substr($header, 0, 4) : substr($header, 4, 4);
                if ($name === 'ANIM' || $name === 'acTL') {
                    return true;
                }
                $length = unpack($webp ? 'Vlength' : 'Nlength', $webp ? substr($header, 4, 4) : substr($header, 0, 4));
                if (! is_array($length) || ! isset($length['length']) || ! is_int($length['length'])) {
                    throw new MediaFailure('image_decode_failed', 'Invalid image chunk length.');
                }
                $bytes = $length['length'];
                $skip = $bytes + ($webp ? $bytes % 2 : 4);
                $offset = ftell($stream);
                if (++$chunks > 4096 || $offset === false || $skip > $fileBytes - $offset
                    || fseek($stream, $skip, SEEK_CUR) !== 0) {
                    throw new MediaFailure('image_decode_failed', 'Invalid image chunk offset.');
                }
            }

            return false;
        } finally {
            fclose($stream);
        }
    }
}
