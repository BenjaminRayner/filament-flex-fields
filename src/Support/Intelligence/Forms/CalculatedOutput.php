<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Forms;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation\CalculationResult;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\CalculationOutputRule;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

/** Shared validation/persistence boundary for typed plans and schema formulas. */
final class CalculatedOutput
{
    /** @param Closure(Get, Field): CalculationResult $calculate */
    public static function bind(Field $field, Closure $calculate, string $key, string $prefix = '', bool $asString = false): void
    {
        $value = static function (Get $get, Field $component) use ($calculate, $key, $asString): float|int|string|null {
            $result = $calculate($get, $component)->values[$key] ?? null;

            return $result !== null && $asString ? (string) $result : $result;
        };

        $field->disabled()->dehydrated()
            ->rule(fn (Get $get, Field $component): CalculationOutputRule => new CalculationOutputRule(fn (): CalculationResult => $calculate($get, $component)))
            ->afterStateHydrated(fn (Get $get, Field $component) => $component->state($value($get, $component)))
            ->beforeStateDehydrated(function (Get $get, Field $component) use ($calculate, $prefix): void {
                $result = $calculate($get, $component);
                if ($result->successful()) {
                    return;
                }

                $parent = $component->getContainer()->getStatePath();
                $errors = [];
                foreach ($result->errors as $input => $message) {
                    $path = $input === 'calculation'
                        ? $component->getStatePath()
                        : ($parent !== '' ? $parent.'.' : '').$prefix.$input;
                    $errors[$path] = $message;
                }
                throw ValidationException::withMessages($errors);
            })
            ->dehydrateStateUsing($value);
    }
}
