<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Support\SignatureSvg;

final class SignaturePlaygroundFixture
{
    public static function svg(): string
    {
        return self::load('signature-readonly-preview.normalized.svg');
    }

    public static function replaySvg(): string
    {
        return self::load('signature-replay-preview.svg');
    }

    private static function load(string $filename): string
    {
        $path = dirname(__DIR__, 3).'/resources/fixtures/'.$filename;
        $svg = is_readable($path) ? SignatureSvg::normalize(file_get_contents($path) ?: null) : null;

        if ($svg === null) {
            throw new \RuntimeException('Build the normalized playground signature fixture before rendering its examples.');
        }

        return $svg;
    }
}
