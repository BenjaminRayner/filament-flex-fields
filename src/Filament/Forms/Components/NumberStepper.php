<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\HasNumberLocale;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NumberStepper\ConfiguresStepperAppearance;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NumberStepper\ConfiguresStepperValue;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NumberStepper\PresentsStepper;
use Bjanczak\FilamentFlexFields\StateCasts\NumberStepperStateCast;
use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use Closure;
use Filament\Forms\Components\Contracts\CanHaveNumericState;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** Numeric policy, presentation and transport remain independently configurable. */
class NumberStepper extends Field implements CanHaveNumericState
{
    use ConfiguresStepperAppearance;
    use ConfiguresStepperValue;
    use HasControlSize;
    use HasNumberLocale;
    use PresentsStepper;

    protected string $view = 'filament-flex-fields::forms.components.number-stepper';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(static function (NumberStepper $component): Closure {
            $policy = $component->getNumericPolicy();

            return static function (string $attribute, mixed $value, Closure $fail) use ($component, $policy): void {
                if (($value === null || $value === '') && $component->isNullable()) {
                    return;
                }
                $number = NumericValue::parse($value);
                if ($number === null || ($component->isInteger() && floor((float) $number) !== (float) $number)) {
                    $fail(__('validation.numeric', ['attribute' => $attribute]));

                    return;
                }
                if ($policy['min'] !== null && $number < $policy['min']) {
                    $fail(__('validation.min.numeric', ['attribute' => $attribute, 'min' => $policy['min']]));
                }
                if ($policy['max'] !== null && $number > $policy['max']) {
                    $fail(__('validation.max.numeric', ['attribute' => $attribute, 'max' => $policy['max']]));
                }
            };
        });
    }

    public function isNumeric(): bool
    {
        return true;
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new NumberStepperStateCast($this->isNullable(), $this->isInteger())];
    }
}
