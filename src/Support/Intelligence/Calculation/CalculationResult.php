<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation;

use Illuminate\Validation\ValidationException;

/** Only a complete successful calculation exposes values for persistence. */
final readonly class CalculationResult
{
    /**
     * @param  array<string, float|int>  $values
     * @param  array<string, string>  $errors
     * @param  list<string>  $evaluated
     */
    public function __construct(public array $values, public array $errors = [], public array $evaluated = []) {}

    public function successful(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, float|int> */
    public function validated(string $prefix = ''): array
    {
        if (! $this->successful()) {
            $messages = [];
            foreach ($this->errors as $key => $message) {
                $messages[$prefix.$key] = $message;
            }
            throw ValidationException::withMessages($messages);
        }

        return $this->values;
    }
}
