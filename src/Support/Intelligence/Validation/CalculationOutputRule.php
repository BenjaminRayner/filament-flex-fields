<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Validation;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationResult;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validate the graph even when an invalid calculation has cleared its preview. */
final readonly class CalculationOutputRule implements ValidationRule
{
    public bool $implicit;

    /** @param Closure(): CalculationResult $calculate */
    public function __construct(private Closure $calculate)
    {
        $this->implicit = true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = ($this->calculate)();
        foreach ($result->errors as $message) {
            $fail($message);
        }
    }
}
