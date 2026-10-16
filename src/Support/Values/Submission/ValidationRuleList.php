<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values\Submission;

use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use Stringable;

/** Shared admission for trusted PHP rules, never rules selected by a request. */
final class ValidationRuleList
{
    /** @param list<string|object> $rules */
    public static function assert(array $rules, bool $allowEmpty = false): void
    {
        if (! array_is_list($rules) || (! $allowEmpty && $rules === []) || count($rules) > 128) {
            throw new InvalidArgumentException('A submitted value needs a bounded nonempty rule list.');
        }
        foreach ($rules as $rule) {
            if (! (is_string($rule) && trim($rule) !== '' && strlen($rule) <= 4096)
                && ! $rule instanceof ValidationRule && ! $rule instanceof Rule
                && ! $rule instanceof Closure && ! $rule instanceof Stringable) {
                throw new InvalidArgumentException('Submitted value rules must be trusted Laravel validation rules.');
            }
        }
    }
}
