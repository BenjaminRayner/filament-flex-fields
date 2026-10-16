<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rating\Validation;

use Bjanczak\FilamentFlexFields\Support\Rating\RatingValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class RatingRule implements ValidationRule
{
    public function __construct(private int $max, private bool $fractional) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $number = RatingValue::numeric($value);
        if ($number === null) {
            $fail('validation.numeric')->translate();
        } elseif ($number < 0 || $number > $this->max) {
            $fail('validation.between.numeric')->translate(['min' => 0, 'max' => $this->max]);
        } elseif (! $this->fractional && floor($number) !== $number) {
            $fail('validation.integer')->translate();
        }
    }
}
