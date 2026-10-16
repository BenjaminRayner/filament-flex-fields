<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\PriceRange\Validation;

use Bjanczak\FilamentFlexFields\Support\PriceRange\RangeConstraints;
use Bjanczak\FilamentFlexFields\Support\PriceRange\RangeValues;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class RangeRule implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private readonly RangeConstraints $constraints) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }
        $pair = RangeValues::pair($value);
        if ($pair === null) {
            $fail('filament-flex-fields::default.validation.price_range.invalid')->translate();

            return;
        }
        if (! $this->constraints->accepts($pair['min']) || ! $this->constraints->accepts($pair['max'])) {
            $fail('filament-flex-fields::default.validation.price_range.out_of_bounds')->translate();

            return;
        }
        if ($pair['min'] > $pair['max']) {
            $fail('filament-flex-fields::default.validation.price_range.min_greater_than_max')->translate();
        }
    }
}
