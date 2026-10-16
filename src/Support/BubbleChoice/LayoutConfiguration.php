<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\BubbleChoice;

use InvalidArgumentException;

final class LayoutConfiguration
{
    /** @param array{size: float, minSize: float, gutter: float, provideProps: bool, numCols: int, fringeWidth: float, yRadius: float, xRadius: float, cornerRadius: float, compact: bool, gravitation: float} $base
     * @return array{size: float, minSize: float, gutter: float, provideProps: bool, numCols: int, fringeWidth: float, yRadius: float, xRadius: float, cornerRadius: float, compact: bool, gravitation: float} */
    public static function resolve(array $base, mixed $overrides): array
    {
        if (! is_array($overrides) || array_diff_key($overrides, $base) !== []) {
            throw new InvalidArgumentException('Unknown bubble layout options.');
        }
        foreach ($overrides as $key => $value) {
            if (in_array($key, ['compact', 'provideProps'], true)) {
                if (! is_bool($value)) {
                    throw new InvalidArgumentException("Bubble layout [{$key}] must be boolean.");
                }
            } elseif (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException("Bubble layout [{$key}] must be numeric.");
            }
        }
        $merged = array_replace($base, $overrides);
        $numeric = function (string $key) use ($merged): float {
            $value = $merged[$key];
            if ((! is_float($value) && ! is_int($value)) || ! is_finite((float) $value) || $value < 0 || $value > 10000) {
                throw new InvalidArgumentException("Bubble layout [{$key}] must be finite and between 0 and 10000.");
            }

            return (float) $value;
        };
        $boolean = function (string $key) use ($merged): bool {
            if (! is_bool($merged[$key])) {
                throw new InvalidArgumentException("Bubble layout [{$key}] must be boolean.");
            }

            return $merged[$key];
        };
        $size = $numeric('size');
        $minSize = $numeric('minSize');
        $columns = $numeric('numCols');
        if ($size < 1 || $minSize > $size || $columns < 1 || $columns > OptionCatalog::LIMIT || floor($columns) !== $columns) {
            throw new InvalidArgumentException('Invalid bubble diameter, minimum diameter or column count.');
        }
        $x = $numeric('xRadius');
        $y = $numeric('yRadius');

        return [
            'size' => $size, 'minSize' => $minSize, 'gutter' => $numeric('gutter'),
            'provideProps' => $boolean('provideProps'), 'numCols' => (int) $columns,
            'fringeWidth' => $numeric('fringeWidth'), 'yRadius' => $y, 'xRadius' => $x,
            'cornerRadius' => min($numeric('cornerRadius'), $x, $y),
            'compact' => $boolean('compact'), 'gravitation' => $numeric('gravitation'),
        ];
    }
}
