<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexSlider\ConfiguresSlider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexSlider\ConfiguresSliderButton;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexSlider\PresentsSlider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexSlider\PresentsSliderPips;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\HasNumberLocale;
use Bjanczak\FilamentFlexFields\StateCasts\FlexSliderStateCast;
use Bjanczak\FilamentFlexFields\Support\Slider\SliderPolicy;
use Closure;
use Filament\Forms\Components\Slider;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Filament\Schemas\Components\StateCasts\SliderStateCast;
use Illuminate\Contracts\Support\Htmlable;

/** Framework configuration remains compatible; FLEX owns rendering, interaction and motion. */
class FlexSlider extends Slider
{
    use ConfiguresSlider;
    use ConfiguresSliderButton;
    use HasControlSize;
    use HasNumberLocale;
    use PresentsSlider;
    use PresentsSliderPips;

    protected string $view = 'filament-flex-fields::forms.components.flex-slider';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule(static function (FlexSlider $component): Closure {
            SliderPolicy::assertConfiguration($component);

            return static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                if (! SliderPolicy::validState($component, $value)) {
                    $fail(__('validation.numeric', ['attribute' => $attribute]));
                }
            };
        });
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        $casts = array_values(array_filter(parent::getDefaultStateCasts(), fn (StateCast $cast): bool => ! ($cast instanceof SliderStateCast)));
        $casts[] = new FlexSliderStateCast;

        return $casts;
    }

    /** @return array<string, mixed> */
    public function getSliderRuntimeConfiguration(): array
    {
        SliderPolicy::assertConfiguration($this);

        return [
            'minValue' => $this->getMinValue(), 'maxValue' => $this->getMaxValue(),
            'step' => $this->getStep(), 'decimalPlaces' => $this->getDecimalPlaces(),
            'isDisabled' => $this->isDisabled(), 'isRtl' => $this->isRtl(), 'isVertical' => $this->isVertical(),
            'behavior' => $this->getBehaviorForJs(), 'minDifference' => $this->getMinDifference(), 'maxDifference' => $this->getMaxDifference(),
            'rangePadding' => $this->getRangePadding(), 'nonLinearPoints' => $this->getNonLinearPoints(),
            'fillTrack' => $this->resolveConnectForChrome(), 'hasTooltips' => $this->hasTooltips(),
            'pipsMode' => $this->getPipsMode()?->value, 'pipsValues' => $this->getPipsValues(),
            'pipsDensity' => $this->getPipsDensity(), 'arePipsStepped' => $this->arePipsStepped(),
            'serverRenderedPips' => $this->shouldRenderServerPips(),
            'prefix' => $this->getDisplayPrefix(), 'suffix' => $this->getDisplaySuffix(), 'locale' => $this->getLocale(),
            'initialNormalizedValues' => $this->getNormalizedStateValues(), 'handleLabel' => strip_tags($this->getLabel() instanceof Htmlable ? $this->getLabel()->toHtml() : ($this->getLabel() ?? '')),
            'iconButton' => $this->getIconButtonConfiguration(),
            'thumbStyle' => $this->getThumbStyle(), 'elastic' => $this->hasElasticMotion(),
            'marks' => $this->getMarks(), 'stretchLimit' => $this->getStretchLimit(),
            'detentDistance' => $this->getDetentDistance(), 'haptics' => $this->hasHaptics(), 'largeStep' => $this->getLargeStep(),
        ];
    }
}
