<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Filament\Forms\Components\Slider\Enums\Behavior;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\RawJs;

class FlexSliderPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'flex_slider__step_demo' => 50,
            'flex_slider__elastic_pill' => 64,
            'flex_slider__elastic_circle' => 64,
            'flex_slider__speed_pill' => 1,
            'flex_slider__speed_circle' => 1,
            'flex_slider__circle_range' => [45, 55],
            'flex_slider__circle_fixed' => [25, 75],
            'flex_slider__elastic_rtl' => 64,
            'flex_slider__elastic_disabled' => 64,
            'flex_slider__button_pill' => 64,
            'flex_slider__button_circle' => 64,
            'flex_slider__button_only' => 64,
            'flex_slider__button_end' => 30,
            'flex_slider__button_disabled' => 64,
            'flex_slider__sm' => 0,
            'flex_slider__md' => 50,
            'flex_slider__lg' => 100,
            'flex_slider__default' => 40,
            'flex_slider__secondary' => 60,
            'flex_slider__volume' => 65,
            'flex_slider__price_range' => [20, 80],
            'flex_slider__range_mid' => [25, 75],
            'flex_slider__tooltips' => 35,
            'flex_slider__tooltips_currency' => 42.5,
            'flex_slider__pips' => 50,
            'flex_slider__track_label' => 7,
            'flex_slider__hide_thumb' => 55,
            'flex_slider__value_start' => 30,
            'flex_slider__value_center' => 50,
            'flex_slider__value_end' => 70,
            'flex_slider__disabled' => 40,
        ];
    }

    public function section(): Section
    {
        return Section::make('Flex Slider')
            ->description('Compare pill and circle handles, elastic motion, rolling numbers, magnetic marks and validated ranges.')
            ->extraAttributes(['class' => 'fff-playground-section'])
            ->schema([
                Section::make('Pill thumb & step dots')
                    ->description('Capsule handle inside fill and optional in-track step markers via showStepDots().')
                    ->compact()
                    ->schema([
                        FlexSlider::make('flex_slider__step_demo')
                            ->hiddenLabel()
                            ->trackLabel('Price')
                            ->range(minValue: 0, maxValue: 100)
                            ->step(10)
                            ->showStepDots()
                            ->fillTrack()
                            ->tooltips(RawJs::make('`$${$value}`'))
                            ->default(50),
                    ]),
                Section::make('Elastic · two handle styles')
                    ->description('The same icons, magnetic marks and elastic motion with a pill inside the rail or a circle outside it.')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            $this->single('flex_slider__elastic_pill', 'Volume · pill')
                                ->fillColor('#8B5CF6')
                                ->default(64)->valuePlacement('header')->suffix('%')->elastic()->tooltips()->haptics()
                                ->startIcon('hugeicons-volume-low')->endIcon('hugeicons-volume-high')
                                ->marks([0, ['value' => 50, 'label' => '50%'], 100]),
                            $this->single('flex_slider__elastic_circle', 'Volume · circle')
                                ->color('success')
                                ->default(64)->valuePlacement('header')->suffix('%')->thumbStyle('circle')->elastic()->tooltips()->haptics()
                                ->startIcon('hugeicons-volume-low')->endIcon('hugeicons-volume-high')
                                ->marks([0, ['value' => 50, 'label' => '50%'], 100]),
                            $this->single('flex_slider__speed_pill', 'Playback · pill')
                                ->color('info')
                                ->range(0.5, 2)->step(0.1)->default(1)->valuePlacement('header')->suffix('×')->elastic()->tooltips()
                                ->marks([['value' => 0.5, 'label' => '0.5×'], ['value' => 1, 'label' => '1×'], ['value' => 1.5, 'label' => '1.5×'], ['value' => 2, 'label' => '2×']]),
                            $this->single('flex_slider__speed_circle', 'Playback · circle')
                                ->fillColor('#F43F5E')
                                ->range(0.5, 2)->step(0.1)->default(1)->valuePlacement('header')->suffix('×')->thumbStyle('circle')->elastic()->tooltips()
                                ->marks([['value' => 0.5, 'label' => '0.5×'], ['value' => 1, 'label' => '1×'], ['value' => 1.5, 'label' => '1.5×'], ['value' => 2, 'label' => '2×']]),
                            $this->single('flex_slider__circle_range', 'Circle · two handles')
                                ->fillColor('#14B8A6')
                                ->default([45, 55])->rangeHandles()->thumbStyle('circle')
                                ->fillTrack([false, true, false])->valuePlacement('header')->suffix('%')
                                ->elastic()->tooltips()->marks([0, ['value' => 50, 'label' => '50%'], 100]),
                            $this->single('flex_slider__circle_fixed', 'Circle · fixed interval')
                                ->default([25, 75])->rangeHandles()->thumbStyle('circle')
                                ->fillTrack([false, true, false])->valuePlacement('header')->suffix('%')
                                ->elastic()->tooltips()->behavior([Behavior::Tap, Behavior::Drag, Behavior::Fixed]),
                            $this->single('flex_slider__elastic_rtl', 'Elastic · RTL')
                                ->default(64)->rtl()->thumbStyle('circle')->elastic()->tooltips()->suffix('%'),
                            $this->single('flex_slider__elastic_disabled', 'Elastic · disabled')
                                ->default(64)->thumbStyle('circle')->elastic()->tooltips()->disabled(),
                        ]),
                    ]),
                Section::make('Icon buttons')
                    ->description('Ordinary side icons stay decorative. Add a separate circular button to switch to a chosen value and restore the previous value.')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            $this->single('flex_slider__button_pill', 'Volume button · pill')
                                ->default(64)->suffix('%')->valuePlacement('header')->elastic()->tooltips()
                                ->iconButton('hugeicons-volume-high')->iconButtonActiveIcon('hugeicons-volume-off')
                                ->iconButtonLabel('Mute')->iconButtonActiveLabel('Restore volume')
                                ->endIcon('hugeicons-volume-high'),
                            $this->single('flex_slider__button_circle', 'Volume button · circle')
                                ->default(64)->suffix('%')->valuePlacement('header')->thumbStyle('circle')->elastic()->tooltips()
                                ->iconButton('hugeicons-volume-high')->iconButtonActiveIcon('hugeicons-volume-off')
                                ->iconButtonLabel('Mute')->iconButtonActiveLabel('Restore volume')
                                ->endIcon('hugeicons-volume-high'),
                            $this->single('flex_slider__button_end', 'Brightness preset · end button')
                                ->default(30)->suffix('%')->valuePlacement('header')->thumbStyle('circle')->elastic()->tooltips()
                                ->startIcon('hugeicons-sun-03')->iconButton('hugeicons-sun-03')
                                ->iconButtonValue(100)->iconButtonPosition('end')
                                ->iconButtonLabel('Maximum brightness')->iconButtonActiveLabel('Restore brightness'),
                            $this->single('flex_slider__button_disabled', 'Volume button · disabled')
                                ->default(64)->suffix('%')->valuePlacement('header')->thumbStyle('circle')->disabled()
                                ->iconButton('hugeicons-volume-high')->iconButtonActiveIcon('hugeicons-volume-off')
                                ->iconButtonLabel('Mute')->iconButtonActiveLabel('Restore volume'),
                            $this->single('flex_slider__button_only', 'Single button · circle')
                                ->fillColor('#0EA5E9')
                                ->default(64)->suffix('%')->valuePlacement('header')->thumbStyle('circle')->elastic()->tooltips()
                                ->iconButton('hugeicons-volume-high')->iconButtonActiveIcon('hugeicons-volume-off')
                                ->iconButtonLabel('Mute')->iconButtonActiveLabel('Restore volume'),
                        ]),
                    ]),
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->extraAttributes(['class' => 'fff-playground-variants'])
                    ->schema([
                        $this->single('flex_slider__sm', 'Slider Label')
                            ->size('sm')
                            ->default(0),
                        $this->single('flex_slider__md', 'Slider Label')
                            ->default(50),
                        $this->single('flex_slider__lg', 'Slider Label')
                            ->size('lg')
                            ->default(100),
                    ]),
                Section::make('Variants')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                $this->single('flex_slider__default', 'Slider Label'),
                                $this->single('flex_slider__secondary', 'Slider Label')
                                    ->variant('secondary'),
                            ]),
                    ]),
                Section::make('Range')
                    ->compact()
                    ->schema([
                        FlexSlider::make('flex_slider__price_range')
                            ->hiddenLabel()
                            ->range(0, 100)
                            ->step(10)
                            ->fillTrack([false, true, false])
                            ->default([20, 80])
                            ->showValue(),
                        FlexSlider::make('flex_slider__range_mid')
                            ->hiddenLabel()
                            ->range(0, 100)
                            ->step(5)
                            ->fillTrack([false, true, false])
                            ->default([25, 75])
                            ->showValue(),
                    ]),
                Section::make('Range & step')
                    ->compact()
                    ->schema([
                        $this->single('flex_slider__volume', 'Volume')
                            ->range(0, 100)
                            ->step(5)
                            ->default(65),
                        $this->single('flex_slider__track_label', 'Guests')
                            ->range(1, 10)
                            ->step(1)
                            ->trackLabel('Guests')
                            ->default(7),
                    ]),
                Section::make('Tooltips & pips')
                    ->compact()
                    ->schema([
                        FlexSlider::make('flex_slider__tooltips')
                            ->hiddenLabel()
                            ->trackLabel('Opacity')
                            ->range(0, 100)
                            ->step(5)
                            ->suffix('%')
                            ->fillTrack()
                            ->tooltips()
                            ->default(35),
                        FlexSlider::make('flex_slider__tooltips_currency')
                            ->hiddenLabel()
                            ->trackLabel('Price')
                            ->range(minValue: 0, maxValue: 100)
                            ->step(0.5)
                            ->fillTrack()
                            ->tooltips(RawJs::make('`$${$value.toFixed(2)}`'))
                            ->default(42.5),
                        $this->single('flex_slider__pips', 'Rating')
                            ->range(0, 100)
                            ->step(10)
                            ->pips(PipsMode::Steps, 5),
                    ]),
                Section::make('Footer value position')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])
                            ->schema([
                                $this->single('flex_slider__value_start', 'Slider Label')
                                    ->valuePosition('start'),
                                $this->single('flex_slider__value_center', 'Slider Label')
                                    ->valuePosition('center'),
                                $this->single('flex_slider__value_end', 'Slider Label')
                                    ->valuePosition('end'),
                            ]),
                    ]),
                Section::make('Hide thumb until interaction')
                    ->compact()
                    ->schema([
                        $this->single('flex_slider__hide_thumb', 'Slider Label')
                            ->hideThumbUntilInteraction()
                            ->helperText('Grip handle appears on hover or while dragging'),
                    ]),
                Section::make('Disabled')
                    ->compact()
                    ->schema([
                        $this->single('flex_slider__disabled', 'Slider Label')
                            ->disabled(),
                    ]),
            ]);
    }

    protected function single(string $name, string $label): FlexSlider
    {
        return FlexSlider::make($name)
            ->hiddenLabel()
            ->trackLabel($label)
            ->range(0, 100)
            ->step(1)
            ->fillTrack()
            ->showValue();
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [$this->section()];
    }
}
