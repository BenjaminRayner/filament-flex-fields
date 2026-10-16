<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\SwitchField;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class BooleanRule implements ValidationRule
{
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_bool(BooleanValue::normalize($value))) {
            $fail('validation.boolean')->translate();
        }
    }
}
