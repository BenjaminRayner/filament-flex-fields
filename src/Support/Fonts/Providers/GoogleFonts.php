<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Fonts\Providers;

use Bjanczak\FilamentFlexFields\Support\Fonts\Data\FontFamily;
use RuntimeException;

/** Bounded immutable snapshot shared by requests; no provider request in a form render. */
final class GoogleFonts
{
    /** @var array<string, FontFamily>|null */
    private static ?array $families = null;

    private static string $revision = '';

    /** @return array<string, FontFamily> */
    public static function families(): array
    {
        if (self::$families !== null) {
            return self::$families;
        }
        $file = dirname(__DIR__, 4).'/resources/data/fonts/google.json';
        $content = file_get_contents($file);
        if ($content === false || strlen($content) > 4194304) {
            throw new RuntimeException('Invalid Google font metadata snapshot.');
        }
        /** @var array{format: int, revision: string, fonts: array<string, array{family: string, category: string, variants: list<string>, subsets: list<string>, variable: bool, axes: array<string, array{min: int|float, max: int|float, default: int|float}>, rank: int, version: string}>} $snapshot */
        $snapshot = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        if ($snapshot['format'] !== 1 || count($snapshot['fonts']) > 10000) {
            throw new RuntimeException('Unsupported Google font catalog.');
        }
        $families = [];
        foreach ($snapshot['fonts'] as $id => $data) {
            $families[$id] = new FontFamily($id, $data['family'], $data['category'], $data['variants'], $data['subsets'], $data['variable'], $data['rank'], $data['version'], axes: $data['axes']);
        }
        self::$revision = $snapshot['revision'];

        return self::$families = $families;
    }

    public static function revision(): string
    {
        self::families();

        return self::$revision;
    }
}
