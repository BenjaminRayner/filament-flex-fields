<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceCards\Presentation;

final class ImageSizeStyles
{
    /**
     * @return array<string, string> */
    public static function for(string $size): array
    {
        return match ($size) {
            'sm' => [
                '--fff-image-choice-cards-gap' => '0.5rem',
                '--fff-image-choice-cards-footer-p' => '0.5rem 0.625rem',
                '--fff-image-choice-cards-footer-band' => '2.25rem',
                '--fff-image-choice-cards-label-size' => '0.75rem',
                '--fff-image-choice-cards-indicator-size' => '1rem',
                '--fff-image-choice-cards-indicator-icon-size' => '0.625rem',
            ],
            'lg' => [
                '--fff-image-choice-cards-gap' => '1rem',
                '--fff-image-choice-cards-footer-p' => '0.875rem 1rem',
                '--fff-image-choice-cards-footer-band' => '3.25rem',
                '--fff-image-choice-cards-label-size' => '1rem',
                '--fff-image-choice-cards-indicator-size' => '1.375rem',
                '--fff-image-choice-cards-indicator-icon-size' => '0.875rem',
            ],
            default => [
                '--fff-image-choice-cards-gap' => '0.75rem',
                '--fff-image-choice-cards-footer-p' => '0.625rem 0.75rem',
                '--fff-image-choice-cards-footer-band' => '2.75rem',
                '--fff-image-choice-cards-label-size' => '0.875rem',
                '--fff-image-choice-cards-indicator-size' => '1.125rem',
                '--fff-image-choice-cards-indicator-icon-size' => '0.75rem',
            ],
        };
    }
}
