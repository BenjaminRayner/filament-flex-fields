<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final readonly class NumericInputRule implements ValidationRule
{
    public function __construct(private NumericInput $contract) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $this->contract->validate($value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
