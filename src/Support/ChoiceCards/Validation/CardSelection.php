<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceCards\Validation;

use Bjanczak\FilamentFlexFields\Support\ChoiceLists\Validation\SelectionLimits;
use Bjanczak\FilamentFlexFields\Support\Select\Validation\StaticOptionSelection;
use Closure;

final class CardSelection
{
    /**
     * @param list<string> $allowed */
    public static function validate(mixed $value, bool $multiple, array $allowed, SelectionLimits $limits, bool $required, string $messages, Closure $fail): void
    {
        if (! $multiple) {
            if ($value !== null && ((! is_string($value) && ! is_int($value)) || ! in_array((string) $value, $allowed, true))) {
                $fail(__('validation.in', ['attribute' => 'selection']));
            }

            return;
        }
        if (! is_array($value)) {
            $fail(__('validation.array', ['attribute' => 'selection']));

            return;
        }
        if (! (new StaticOptionSelection)->acceptsList($allowed, $value)) {
            $fail(__('filament-flex-fields::default.validation.'.$messages.'.invalid_option'));

            return;
        }
        if ($violation = $limits->violation(count($value), $required)) {
            $fail(__('filament-flex-fields::default.validation.'.$messages.'.'.$violation, ['count' => $limits->countFor($violation)]));
        }
    }
}
