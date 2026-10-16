<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceCards\Presentation;

final class CardSizeStyles
{
    /**
     * @return array<string, string> */
    public static function for(string $size): array
    {
        return match ($size) {
            'sm' => [
                '--fff-choice-cards-gap' => '0.375rem',
                '--fff-choice-cards-p' => '0.625rem',
                '--fff-choice-cards-radius' => '0.5rem',
                '--fff-choice-cards-indicator-inset' => '0.625rem',
                '--fff-choice-cards-indicator-size' => '0.875rem',
                '--fff-choice-cards-indicator-check-size' => '1rem',
                '--fff-choice-cards-indicator-icon-size' => '0.5rem',
                '--fff-choice-cards-content-pr' => '1rem',
                '--fff-choice-cards-featured-content-pr' => '1.5rem',
                '--fff-choice-cards-label-size' => '0.8125rem',
                '--fff-choice-cards-body-size' => '0.6875rem',
                '--fff-choice-cards-price-size' => '0.8125rem',
                '--fff-choice-cards-featured-price-size' => '1.125rem',
                '--fff-choice-cards-icon-box-size' => '1.375rem',
                '--fff-choice-cards-icon-size' => '0.75rem',
            ],
            'lg' => [
                '--fff-choice-cards-gap' => '1rem',
                '--fff-choice-cards-p' => '1.5rem',
                '--fff-choice-cards-radius' => '1rem',
                '--fff-choice-cards-indicator-inset' => '1.5rem',
                '--fff-choice-cards-indicator-size' => '1.25rem',
                '--fff-choice-cards-indicator-check-size' => '1.625rem',
                '--fff-choice-cards-indicator-icon-size' => '1rem',
                '--fff-choice-cards-content-pr' => '2.25rem',
                '--fff-choice-cards-featured-content-pr' => '3rem',
                '--fff-choice-cards-label-size' => '1.125rem',
                '--fff-choice-cards-body-size' => '0.9375rem',
                '--fff-choice-cards-price-size' => '1.125rem',
                '--fff-choice-cards-featured-price-size' => '2rem',
                '--fff-choice-cards-icon-box-size' => '2.75rem',
                '--fff-choice-cards-icon-size' => '1.25rem',
            ],
            default => [
                '--fff-choice-cards-gap' => '0.75rem',
                '--fff-choice-cards-p' => '1rem',
                '--fff-choice-cards-radius' => '0.75rem',
                '--fff-choice-cards-indicator-inset' => '1rem',
                '--fff-choice-cards-indicator-size' => '1rem',
                '--fff-choice-cards-indicator-check-size' => '1.25rem',
                '--fff-choice-cards-indicator-icon-size' => '0.75rem',
                '--fff-choice-cards-content-pr' => '1.5rem',
                '--fff-choice-cards-featured-content-pr' => '2.25rem',
                '--fff-choice-cards-label-size' => '0.9375rem',
                '--fff-choice-cards-body-size' => '0.8125rem',
                '--fff-choice-cards-price-size' => '0.9375rem',
                '--fff-choice-cards-featured-price-size' => '1.5rem',
                '--fff-choice-cards-icon-box-size' => '2rem',
                '--fff-choice-cards-icon-size' => '1rem',
            ],
        };
    }
}
