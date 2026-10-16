<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Matrix\Validation;

use Bjanczak\FilamentFlexFields\Support\ChoiceLists\Validation\SelectionLimits;
use Bjanczak\FilamentFlexFields\Support\Matrix\ChoicePolicy;
use Bjanczak\FilamentFlexFields\Support\Select\Validation\StaticOptionSelection;
use Closure;

final class MatrixSelection
{
    public static function validate(ChoicePolicy $policy, mixed $value, Closure $fail): void
    {
        $message = 'filament-flex-fields::default.validation.matrix_choice.';
        if (! is_array($value)) {
            $fail(__($message.'invalid'));

            return;
        }
        foreach ($value as $key => $rowValue) {
            if (! array_key_exists($key, $policy->rows)) {
                $fail(__($message.'invalid'));

                return;
            }
            if ($policy->multiple) {
                if (! (new StaticOptionSelection)->acceptsList(array_map(strval(...), array_keys($policy->columns)), $rowValue)) {
                    $fail(__($message.'invalid_option'));

                    return;
                }
            } elseif ($rowValue !== null && $rowValue !== '' && (! is_string($rowValue) && ! is_int($rowValue))) {
                $fail(__($message.'invalid_option'));

                return;
            }
        }
        foreach ($policy->rows as $key => $row) {
            $selected = $value[$key] ?? ($policy->multiple ? [] : null);
            $values = $policy->multiple ? $selected : (($selected === null || $selected === '') ? [] : [$selected]);
            foreach ($values as $column) {
                if ($policy->cellDisabled((string) $key, (string) $column, $value)) {
                    $fail(__($message.'invalid_option'));

                    return;
                }
            }
            if ($policy->rowDisabled((string) $key, $value)) {
                continue;
            }
            if ($row['required'] && $values === []) {
                $fail(__($message.'row_required', ['row' => $row['label']]));

                return;
            }
            if ($policy->multiple) {
                $limits = new SelectionLimits($row['min_selections'], $row['max_selections'], null);
                if ($violation = $limits->violation(count($values), $row['required'])) {
                    $fail(__($message.'row_'.$violation, ['row' => $row['label'], 'count' => $limits->countFor($violation)]));

                    return;
                }
            }
        }
    }
}
