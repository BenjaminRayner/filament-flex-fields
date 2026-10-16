<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ColorPicker;

/** A bounded CSS-color grammar shared with the browser, without DOM/CSS evaluation. */
final class ColorValue
{
    public static function valid(mixed $value, bool $alphaEnabled = true): bool
    {
        if (! is_string($value) || strlen($value) > 128) {
            return false;
        }
        $value = trim($value);
        if (preg_match('/^#(?:[\da-f]{3}|[\da-f]{6}|[\da-f]{8})$/iD', $value)) {
            return $alphaEnabled || strlen($value) !== 9 || strtolower(substr($value, -2)) === 'ff';
        }
        if (! preg_match('/^(rgb|rgba|hsl|hsla)\(\s*(\d+(?:\.\d+)?)\s*,\s*(\d+(?:\.\d+)?)(%?)\s*,\s*(\d+(?:\.\d+)?)(%?)(?:\s*,\s*(\d+(?:\.\d+)?))?\s*\)$/iD', $value, $m)) {
            return false;
        }
        $kind = strtolower($m[1]);
        $hasAlpha = str_ends_with($kind, 'a');
        if ($hasAlpha !== isset($m[7])) {
            return false;
        }
        $alpha = isset($m[7]) ? (float) $m[7] : 1;
        if ($alpha > 1 || (! $alphaEnabled && $alpha < 1)) {
            return false;
        }
        if (str_starts_with($kind, 'hsl')) {
            return $m[4] === '%' && $m[6] === '%' && (float) $m[2] <= 360 && (float) $m[3] <= 100 && (float) $m[5] <= 100;
        }
        foreach ([2, 3, 5] as $index) {
            $channel = (float) $m[$index];
            if ($channel > 255 || floor($channel) !== $channel) {
                return false;
            }
        }

        return $m[4] === '' && $m[6] === '';
    }
}
