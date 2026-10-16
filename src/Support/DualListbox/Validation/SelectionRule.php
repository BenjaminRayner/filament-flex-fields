<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DualListbox\Validation;

use Bjanczak\FilamentFlexFields\Support\DualListbox\SelectionValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class SelectionRule implements ValidationRule
{
    public bool $implicit = true;

    /** @param array<string, array{label: string, description: ?string, disabled: bool}> $options */
    public function __construct(private readonly array $options, private readonly ?int $min, private readonly ?int $max, private readonly bool $required)
    {
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('minItems cannot exceed maxItems.');
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! SelectionValue::validShape($value)) {
            $fail('validation.array')->translate();

            return;
        }
        $seen = [];
        foreach ($value as $key) {
            $key = (string) $key;
            if (! isset($this->options[$key]) || isset($seen[$key])) {
                $fail('filament-flex-fields::default.validation.dual_listbox.invalid_option')->translate();

                return;
            }
            $seen[$key] = true;
        }
        $count = count($value);
        if ($this->required && $count === 0) {
            $fail('validation.required')->translate();

            return;
        }
        if ($this->min !== null && $this->min === $this->max && $count !== $this->min) {
            $fail('filament-flex-fields::default.validation.dual_listbox.exact')->translate(['count' => $this->min]);

            return;
        }
        if ($this->min !== null && $count < $this->min) {
            $fail('filament-flex-fields::default.validation.dual_listbox.min')->translate(['count' => $this->min]);
        }
        if ($this->max !== null && $count > $this->max) {
            $fail('filament-flex-fields::default.validation.dual_listbox.max')->translate(['count' => $this->max]);
        }
    }
}
