<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CoverCard\Configuration;

use Bjanczak\FilamentFlexFields\Support\Theme\ThemeTokenMap;
use InvalidArgumentException;

final class CssValue
{
    public static function optional(?string $value, string $property): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        try {
            ThemeTokenMap::validate(['--fff-cover-card-value' => $value]);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException("Invalid Cover Card {$property}: supply a CSS value, not declarations or a URL.", previous: $exception);
        }

        return $value;
    }

    public static function required(string $value, string $property): string
    {
        return self::optional($value, $property)
            ?? throw new InvalidArgumentException("Cover Card {$property} cannot be empty.");
    }
}
