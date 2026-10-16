<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrackSlider\ConfiguresTrackSlider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrackSlider\PresentsTrackSlider;
use Bjanczak\FilamentFlexFields\StateCasts\NumberStepperStateCast;
use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use Closure;
use Filament\Forms\Components\Contracts\CanHaveNumericState;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class TrackSlider extends Field implements CanHaveNumericState
{
    use ConfiguresTrackSlider;
    use HasControlSize;
    use PresentsTrackSlider;

    protected string $view = 'filament-flex-fields::forms.components.track-slider';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(static function (TrackSlider $component): array {
            $component->assertTrackConfiguration();

            return [
                $component->isInteger() ? 'integer' : 'numeric',
                'min:'.$component->getMin(),
                'max:'.$component->getMax(),
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (NumericValue::parse($value) === null) {
                        $fail(__('validation.numeric', ['attribute' => $attribute]));
                    }
                },
            ];
        });
    }

    public function isNumeric(): bool
    {
        return true;
    }

    /**
     * @return array<StateCast>
     */
    public function getDefaultStateCasts(): array
    {
        return [
            ...parent::getDefaultStateCasts(),
            new NumberStepperStateCast(nullable: false, integer: $this->isInteger()),
        ];
    }
}
