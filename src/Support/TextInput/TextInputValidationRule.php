<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TextInput;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class TextInputValidationRule implements ValidationRule
{
    public bool $implicit;

    /** @param array<string, mixed> $options */
    public function __construct(private array $options, private bool $password)
    {
        $this->implicit = true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_scalar($value)) {
            $fail(TextInputMessages::get('invalid_value_error', ['attribute' => $attribute]));

            return;
        }
        $text = (string) $value;
        if ($this->options['enforceCounterLimit']
            && TextInputCounter::count($text, $this->options['counterMode']) > $this->options['counterLimit']) {
            $fail(TextInputMessages::get('counter_limit_error', ['attribute' => $attribute, 'limit' => $this->options['counterLimit'], 'unit' => TextInputMessages::get('counter_units.'.$this->options['counterMode'])]));
        }
        if ($this->password && in_array(false, PasswordPolicy::checks($text, $this->options['passwordRequirements']), true)) {
            $fail(TextInputMessages::get('password_requirement_error', ['attribute' => $attribute]));
        }
    }
}
