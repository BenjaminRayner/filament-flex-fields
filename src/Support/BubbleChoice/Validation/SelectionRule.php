<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\BubbleChoice\Validation;

use Bjanczak\FilamentFlexFields\Support\BubbleChoice\SelectionValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class SelectionRule implements ValidationRule
{
    public bool $implicit = true;

    /** @param array<string|int, array{disabled: bool}> $options */
    public function __construct(private readonly array $options, private readonly ?int $min, private readonly ?int $max, private readonly ?int $exact, private readonly bool $required)
    {
        if (($min !== null && $max !== null && $min > $max)
            || ($exact !== null && (($min !== null && $exact < $min) || ($max !== null && $exact > $max)))) {
            throw new InvalidArgumentException('Inconsistent bubble selection limits.');
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null && ! $this->required && ($this->min ?? 0) === 0 && ($this->exact ?? 0) === 0) {
            return;
        }
        if (! SelectionValue::validShape($value)) {
            $fail('validation.array')->translate();

            return;
        }
        $seen = [];
        foreach ($value as $key) {
            $key = (string) $key;
            if (! isset($this->options[$key]) || $this->options[$key]['disabled'] || isset($seen[$key])) {
                $fail('filament-flex-fields::default.validation.bubble_choice.invalid_option')->translate();

                return;
            }
            $seen[$key] = true;
        }
        $count = count($value);
        if ($this->required && $count === 0) {
            $fail('filament-flex-fields::default.validation.bubble_choice.min')->translate(['count' => 1]);

            return;
        }
        if ($this->exact !== null && $count !== $this->exact) {
            $fail('filament-flex-fields::default.validation.bubble_choice.exact')->translate(['count' => $this->exact]);

            return;
        }
        if ($this->min !== null && $count < $this->min) {
            $fail('filament-flex-fields::default.validation.bubble_choice.min')->translate(['count' => $this->min]);
        }
        if ($this->max !== null && $count > $this->max) {
            $fail('filament-flex-fields::default.validation.bubble_choice.max')->translate(['count' => $this->max]);
        }
    }
}
