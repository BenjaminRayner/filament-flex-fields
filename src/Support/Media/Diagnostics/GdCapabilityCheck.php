<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

/** Local functions/support flags only: no image decoding or temporary files. */
final class GdCapabilityCheck
{
    public function inspect(MediaDiagnosticReport $report, MediaDoctorOptions $options): void
    {
        if ($options->customEncoder) {
            $report->add('custom_encoder_unverified', 'unverified', 'The selected profile uses an application image encoder.',
                'Run the custom encoder capability checks on web and worker processes. This command never resolves or invokes its factory.');

            return;
        }
        $report->add('gd_canvas', function_exists('imagecreatetruecolor') ? 'passed' : 'failed',
            'The bundled GD encoder requires canvas support in this PHP process.',
            'Install or enable GD in each PHP process that performs conversions; a web check does not inspect a separate worker.');
        $support = function_exists('gd_info') ? gd_info() : [];
        foreach ($options->formats ?: ['webp'] as $format) {
            $function = match ($format) {
                'jpg' => 'imagejpeg', 'png' => 'imagepng', 'webp' => 'imagewebp', 'avif' => 'imageavif',
                default => throw new \LogicException('Unvalidated image output format.'),
            };
            $flag = match ($format) {
                'jpg' => 'JPEG Support', 'png' => 'PNG Support', 'webp' => 'WebP Support', 'avif' => 'AVIF Support',
            };
            $status = ! function_exists($function) ? 'failed' : (isset($support[$flag]) ? ($support[$flag] === true ? 'passed' : 'failed') : 'unverified');
            $report->add('gd_output_'.$format, $status,
                'The requested output format requires its GD encoder and format support in this PHP process.',
                'Enable GD support for the requested output format, or select a supported format in your conversions.');
        }
        $report->add('gd_jpeg_orientation', function_exists('exif_read_data') ? 'passed' : 'unverified',
            'Automatic JPEG orientation in the bundled encoder uses EXIF.',
            'Enable EXIF when accepting auto-oriented JPEG sources, or explicitly disable autoOrient for that conversion.');
        $report->add('image_sources_unverified', 'unverified', 'Source decoders and real conversion results depend on the files an application accepts.',
            'Run representative conversions for the accepted source formats, dimensions and budgets on each conversion process.');
    }
}
