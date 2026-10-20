<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Images\GdImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFormat;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageLimits;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Throwable;

/** Tiny synthetic input only; no customer files or application encoder factories. */
final class MediaImageProbe
{
    /** @param list<string> $formats */
    public function inspect(array $formats, MediaProbeReport $report): void
    {
        $source = null;
        $policy = new MediaPolicy(maxBytes: 65536, mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/avif']);
        try {
            // Fixed 16 x 8 truecolor PNG, encoded independently of the installed GD extension.
            $row = "\x00".str_repeat("\x36\x96\xd2", 16);
            $compressed = gzcompress(str_repeat($row, 8));
            if ($compressed === false) {
                $report->add('image_source', false);

                return;
            }
            $png = "\x89PNG\r\n\x1a\n".$this->chunk('IHDR', pack('NNCCCCC', 16, 8, 8, 2, 0, 0, 0))
                .$this->chunk('IDAT', $compressed).$this->chunk('IEND', '');
            $source = PreparedMedia::prepare(MediaSource::bytes($png, 'diagnostic.png'), $policy);
            $report->add('image_source', true);
        } catch (Throwable) {
            $report->add('image_source', false);

            return;
        }
        $outputsClean = true;
        try {
            $encoder = new GdImageEncoder;
            $limits = new ImageLimits(sourcePixels: 256, outputPixels: 64, variants: 4, workingBytes: 16777216);
            foreach ($formats as $format) {
                $report->check('image_'.$format, static function () use ($source, $policy, $encoder, $limits, $format, &$outputsClean): bool {
                    $conversion = new ImageConversion('diagnostic', width: 8, height: 4, format: ImageFormat::from($format), autoOrient: false);
                    $output = $encoder->encode($source, $conversion, $limits, $policy);
                    try {
                        $size = @getimagesize($output->path);

                        return $size !== false && $size[0] === 8 && $size[1] === 4
                            && $size['mime'] === $conversion->format->mimeType() && $output->file->bytes <= 65536;
                    } finally {
                        $output->close();
                        $outputsClean = $outputsClean && ! is_file($output->path);
                    }
                });
            }
        } finally {
            $source->close();
            $report->add('image_cleanup', $outputsClean && ! is_file($source->path));
        }
    }

    private function chunk(string $type, string $bytes): string
    {
        return pack('N', strlen($bytes)).$type.$bytes.pack('N', crc32($type.$bytes));
    }
}
