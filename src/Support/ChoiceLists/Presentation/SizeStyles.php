<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceLists\Presentation;

use InvalidArgumentException;

final class SizeStyles
{
    /** @return array<string, string> */
    public static function for(string $size, string $prefix): array
    {
        if (! in_array($size, ['sm', 'md', 'lg'], true)) {
            throw new InvalidArgumentException("Choice list size [{$size}] is not supported.");
        }
        $styles = self::metrics($size);
        $result = [];
        foreach ($styles as $key => $value) {
            $result[str_replace('choice-list', $prefix, $key)] = $value;
        }

        return $result;
    }

    /** @return array<string, string> */
    private static function metrics(string $size): array
    {
        return match ($size) {
            'sm' => [
                '--fff-choice-list-radius' => '0.625rem',
                '--fff-choice-list-row-py' => '0.5rem',
                '--fff-choice-list-row-px' => '0.625rem',
                '--fff-choice-list-gap' => '0.625rem',
                '--fff-choice-list-label-size' => '0.8125rem',
                '--fff-choice-list-body-size' => '0.6875rem',
                '--fff-choice-list-icon-size' => '0.9375rem',
                '--fff-choice-list-icon-box-size' => '1.5rem',
                '--fff-choice-list-indicator-size' => '0.875rem',
                '--fff-choice-list-indicator-icon-size' => '0.5rem',
                '--fff-choice-list-lock-size' => '0.875rem',
            ],
            'lg' => [
                '--fff-choice-list-radius' => '1rem',
                '--fff-choice-list-row-py' => '0.875rem',
                '--fff-choice-list-row-px' => '1rem',
                '--fff-choice-list-gap' => '1rem',
                '--fff-choice-list-label-size' => '1rem',
                '--fff-choice-list-body-size' => '0.875rem',
                '--fff-choice-list-icon-size' => '1.125rem',
                '--fff-choice-list-icon-box-size' => '2.25rem',
                '--fff-choice-list-indicator-size' => '1.25rem',
                '--fff-choice-list-indicator-icon-size' => '1rem',
                '--fff-choice-list-lock-size' => '1.125rem',
            ],
            default => [
                '--fff-choice-list-radius' => '0.75rem',
                '--fff-choice-list-row-py' => '0.625rem',
                '--fff-choice-list-row-px' => '0.75rem',
                '--fff-choice-list-gap' => '0.75rem',
                '--fff-choice-list-label-size' => '0.875rem',
                '--fff-choice-list-body-size' => '0.75rem',
                '--fff-choice-list-icon-size' => '1rem',
                '--fff-choice-list-icon-box-size' => '1.75rem',
                '--fff-choice-list-indicator-size' => '1rem',
                '--fff-choice-list-indicator-icon-size' => '0.75rem',
                '--fff-choice-list-lock-size' => '1rem',
            ],
        };
    }
}
