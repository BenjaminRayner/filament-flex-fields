<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Matrix\Presentation;

final class SizeStyles
{
    /**
     * @return array<string,string> */
    public static function for(string $size, bool $indicators = true): array
    {
        $styles = match ($size) {
            'sm' => [
                '--fff-matrix-choice-radius' => '0.875rem',
                '--fff-matrix-choice-frame-padding' => '0.3125rem',
                '--fff-matrix-choice-header-py' => '0.625rem',
                '--fff-matrix-choice-header-px' => '0.5rem',
                '--fff-matrix-choice-row-py' => '0.625rem',
                '--fff-matrix-choice-row-px' => '0.75rem',
                '--fff-matrix-choice-row-label-size' => '0.8125rem',
                '--fff-matrix-choice-column-label-size' => '0.75rem',
                '--fff-matrix-choice-indicator-size' => '0.875rem',
                '--fff-matrix-choice-indicator-icon-size' => '0.5rem',
            ],
            'lg' => [
                '--fff-matrix-choice-radius' => '1.125rem',
                '--fff-matrix-choice-frame-padding' => '0.5rem',
                '--fff-matrix-choice-header-py' => '0.875rem',
                '--fff-matrix-choice-header-px' => '0.75rem',
                '--fff-matrix-choice-row-py' => '0.875rem',
                '--fff-matrix-choice-row-px' => '1rem',
                '--fff-matrix-choice-row-label-size' => '1rem',
                '--fff-matrix-choice-column-label-size' => '0.875rem',
                '--fff-matrix-choice-indicator-size' => '1.25rem',
                '--fff-matrix-choice-indicator-icon-size' => '1rem',
            ],
            default => [
                '--fff-matrix-choice-radius' => '1rem',
                '--fff-matrix-choice-frame-padding' => '0.375rem',
                '--fff-matrix-choice-header-py' => '0.75rem',
                '--fff-matrix-choice-header-px' => '0.625rem',
                '--fff-matrix-choice-row-py' => '0.75rem',
                '--fff-matrix-choice-row-px' => '0.875rem',
                '--fff-matrix-choice-row-label-size' => '0.875rem',
                '--fff-matrix-choice-column-label-size' => '0.8125rem',
                '--fff-matrix-choice-indicator-size' => '1rem',
                '--fff-matrix-choice-indicator-icon-size' => '0.75rem',
            ],
        };
        if (! $indicators) {
            unset($styles['--fff-matrix-choice-indicator-size'], $styles['--fff-matrix-choice-indicator-icon-size']);
        }

        return $styles;
    }
}
