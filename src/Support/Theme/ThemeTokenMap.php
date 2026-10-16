<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Theme;

use InvalidArgumentException;

/** Literal CSS values, never declarations, markup, URLs or executable expressions. */
final class ThemeTokenMap
{
    public const MAX_TOKENS = 512;

    private const ALIASES = [
        'primary' => '--fff-field-focus-border', 'primary_color' => '--fff-field-focus-border',
        'radius' => '--fff-global-radius', 'field_radius' => '--fff-global-radius',
        'menu_radius' => '--fff-global-menu-radius', 'field_bg' => '--fff-field-bg',
        'field_border' => '--fff-field-border', 'field_focus_ring' => '--fff-field-focus-ring',
        'field_focus_ring_width' => '--fff-field-focus-ring-width',
    ];

    /** @param array<string, mixed> $tokens
     * @return array<string, string|int|float>
     */
    public static function validate(array $tokens): array
    {
        if (count($tokens) > self::MAX_TOKENS) {
            throw new InvalidArgumentException('Theme exceeds its token budget.');
        }
        $copy = [];
        $bytes = 0;
        foreach ($tokens as $key => $value) {
            if (! is_string($key) || self::variable($key) === null) {
                throw new InvalidArgumentException('Unknown or invalid FLEX theme token name.');
            }
            if ((! is_string($value) && ! is_int($value) && ! is_float($value))
                || (is_float($value) && ! is_finite($value))) {
                throw new InvalidArgumentException('FLEX theme token must be a finite number or literal CSS value.');
            }
            $literal = (string) $value;
            $bytes += strlen($key) + strlen($literal);
            if ($literal === '' || strlen($literal) > 2048 || $bytes > 65536
                || ! mb_check_encoding($literal, 'UTF-8')
                || preg_match('/[\x00-\x1f\x7f;{}<>\\\\@]|\/\*|\*\/|(?:url|expression|image-set|image|src)\s*\(/i', $literal) === 1) {
                throw new InvalidArgumentException('Invalid or oversized literal FLEX theme token value.');
            }
            // Assignment copies reference-bearing input instead of retaining caller aliases.
            $copy[$key] = $value;
        }

        return $copy;
    }

    public static function variable(string $key): ?string
    {
        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }
        if (strlen($key) > 160) {
            return null;
        }
        if (preg_match('/^--fff-[a-z][a-z0-9_-]*$/D', $key) === 1) {
            return $key;
        }
        if (preg_match('/^fff_[a-z][a-z0-9_-]*$/D', $key) === 1) {
            return '--'.$key;
        }

        return null;
    }
}
