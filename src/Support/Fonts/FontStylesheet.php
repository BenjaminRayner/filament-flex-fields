<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Fonts;

use Bjanczak\FilamentFlexFields\Support\Fonts\Data\FontFamily;
use InvalidArgumentException;

/** Full application fonts are separate from the manager's short-lived preview subsets. */
final class FontStylesheet
{
    /** @param list<int> $weights
     * @param  list<string>  $styles
     */
    public static function google(FontFamily $font, array $weights, array $styles, string $display): ?string
    {
        if ($font->file !== null || ! str_starts_with($font->id, 'google:')) {
            return null;
        }
        self::display($display);
        if (! array_is_list($weights) || $weights === [] || count($weights) > 12
            || ! array_is_list($styles) || $styles === [] || count($styles) > 2) {
            throw new InvalidArgumentException('Invalid font stylesheet variants.');
        }
        foreach ($styles as $style) {
            if (! is_string($style) || ! in_array($style, ['normal', 'italic'], true)) {
                throw new InvalidArgumentException('Invalid font stylesheet style.');
            }
        }
        $variants = [];
        foreach ($weights as $weight) {
            if (! is_int($weight) || $weight < 1 || $weight > 1000) {
                throw new InvalidArgumentException('Font stylesheet weights must be integers from 1 to 1000.');
            }
            foreach ($styles as $style) {
                $variant = $font->variant($weight, $style);
                $italic = $variant['style'] === 'italic' ? 1 : 0;
                $variants[$italic.','.$variant['weight']] = [$italic, $variant['weight']];
            }
        }
        $variants = array_values($variants);
        usort($variants, static fn (array $a, array $b): int => $a <=> $b);
        $includesItalic = array_filter($variants, static fn (array $variant): bool => $variant[0] === 1) !== [];
        $axis = $includesItalic ? ':ital,wght@' : ':wght@';
        $values = array_map(static fn (array $variant): string => $includesItalic ? implode(',', $variant) : (string) $variant[1], $variants);

        return 'https://fonts.googleapis.com/css2?family='.rawurlencode($font->family.$axis.implode(';', $values)).'&display='.$display;
    }

    /** @param array{int, int} $weightRange
     * @param  list<string>  $styles
     */
    public static function variableGoogle(FontFamily $font, array $weightRange, array $styles, string $display): string
    {
        self::display($display);
        $axis = $font->axes['wght'] ?? null;
        if ($font->file !== null || ! str_starts_with($font->id, 'google:') || $axis === null
            || ! array_is_list($weightRange) || count($weightRange) !== 2
            || ! is_int($weightRange[0]) || ! is_int($weightRange[1])
            || $weightRange[0] < $axis['min'] || $weightRange[1] > $axis['max']
            || $weightRange[0] >= $weightRange[1]) {
            throw new InvalidArgumentException('Weight range requires a supported Google variable weight axis.');
        }
        if (! array_is_list($styles) || $styles === [] || count($styles) > 2) {
            throw new InvalidArgumentException('Invalid font stylesheet styles.');
        }
        $italics = [];
        foreach ($styles as $style) {
            if (! in_array($style, ['normal', 'italic'], true)
                || $font->variant(400, $style)['style'] !== $style) {
                throw new InvalidArgumentException('Variable font style is unavailable.');
            }
            $italics[$style === 'italic' ? 1 : 0] = true;
        }
        ksort($italics);
        $range = implode('..', $weightRange);
        $includesItalic = isset($italics[1]);
        $values = array_map(static fn (int $italic): string => $includesItalic ? $italic.','.$range : $range, array_keys($italics));

        return 'https://fonts.googleapis.com/css2?family='.rawurlencode($font->family.($includesItalic ? ':ital,wght@' : ':wght@').implode(';', $values)).'&display='.$display;
    }

    public static function local(FontFamily $font, string $display): string
    {
        self::display($display);
        if ($font->file === null) {
            return '';
        }
        $format = match (strtolower(pathinfo((string) parse_url($font->file, PHP_URL_PATH), PATHINFO_EXTENSION))) {
            'woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype',
            default => throw new InvalidArgumentException('Unsupported local font format.'),
        };
        $blocks = [];
        foreach ($font->variants as $variant) {
            $style = str_ends_with($variant, 'i') ? 'italic' : 'normal';
            $blocks[] = '@font-face{font-family:'.self::quote($font->family).';src:url('.self::quote($font->file).') format('.self::quote($format).');font-weight:'.(int) $variant.';font-style:'.$style.';font-display:'.$display.';}';
        }

        return implode("\n", $blocks);
    }

    /** A quoted CSS string, also safe inside an HTML style raw-text element. */
    public static function quote(string $value): string
    {
        return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\22 ', '<' => '\\3c ', '>' => '\\3e ', "\n" => '\\a ', "\r" => '\\d ', "\0" => '\\fffd ']).'"';
    }

    private static function display(string $display): void
    {
        if (! in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true)) {
            throw new InvalidArgumentException('Unsupported font display mode.');
        }
    }
}
