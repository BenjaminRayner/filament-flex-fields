<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexColorPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SocialLinksField;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class FlexColorPickerPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'flex_color_picker__advanced' => '#6366F1',
            'flex_color_picker__grid' => '#FCA5A5',
            'flex_color_picker__secondary' => '#84D0D0',
            'flex_color_picker__alpha' => 'rgba(244, 63, 94, 0.72)',
            'flex_color_picker__hsl' => 'hsl(262, 83%, 58%)',
            'flex_color_picker__icon' => '#3B82F6',
            'flex_color_picker__swatches' => '#22C55E',
            'flex_color_picker__inline' => '#8B5CF6',
            'flex_color_picker__sliders' => 'hsl(219, 58%, 93%)',
            'flex_color_picker__hex_only' => '#3B82F6',
            'flex_color_picker__palette' => '#EF4444',
            'flex_color_picker__readonly' => '#F59E0B',
        ];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            Section::make('Flex color picker')
                ->description('Advanced HSV panel with eyedropper, or preset grid layout. Stores hex, rgb, hsl, or rgba depending on format().')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    Actions::make([
                        Action::make('shared_overlay_preview')
                            ->label('Open shared dropdowns modal')
                            ->modalHeading('Shared dropdowns · modal preview')
                            ->modalWidth('2xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->schema([
                                FlexColorPickerField::make('preview_color')->label('Modal color')->default('#6366F1')->showSwatches(),
                                SelectField::make('preview_select')->label('Modal select')->options(['one' => 'First option', 'two' => 'Second option'])->default('one'),
                                SocialLinksField::make('preview_social')->label('Modal social links'),
                                FlexDatePicker::make('preview_date')->label('Modal calendar'),
                                ScheduleField::make('preview_schedule')->label('Modal schedule')->timezone('UTC'),
                            ]),
                    ]),
                    FlexColorPickerField::make('flex_color_picker__advanced')
                        ->label('Advanced layout')
                        ->helperText('Saturation/value square, hue and optional alpha sliders, format switcher.')
                        ->alpha()
                        ->columnSpanFull(),
                    FlexColorPickerField::make('flex_color_picker__grid')
                        ->label('Grid layout')
                        ->layout(FlexColorPickerField::LAYOUT_GRID)
                        ->alpha()
                        ->columnSpanFull(),
                    FlexColorPickerField::make('flex_color_picker__secondary')
                        ->label('Secondary variant')
                        ->variant('secondary')
                        ->columnSpanFull(),
                    FlexColorPickerField::make('flex_color_picker__icon')->label('Icon only · opaque')->iconOnly()->alpha(false)->eyedropper(false)->showFields(false),
                    FlexColorPickerField::make('flex_color_picker__swatches')->label('Compact swatches')->swatchOnly()->layout('swatches')->closeOnSelect(),
                    FlexColorPickerField::make('flex_color_picker__sliders')->label('Sliders only · HSL / RGB')->layout('sliders')->hsl()->allowedFormats(['hsl', 'rgb'])->alpha()->eyedropper(false),
                    FlexColorPickerField::make('flex_color_picker__hex_only')->label('HEX only · no format selector')->allowedFormats(['hex'])->eyedropper(false)->channelFields(),
                    FlexColorPickerField::make('flex_color_picker__palette')->label('With swatches')->showSwatches()->showFields(false)->eyedropper(false),
                    FlexColorPickerField::make('flex_color_picker__inline')->label('Inline channels and swatches')->inline()->showSwatches()->alpha(),
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                        ->extraAttributes(['class' => 'fff-playground-variants'])
                        ->schema([
                            FlexColorPickerField::make('flex_color_picker__alpha')
                                ->label('RGBA output')
                                ->rgba()
                                ->alpha()
                                ->eyedropper(false),
                            FlexColorPickerField::make('flex_color_picker__hsl')
                                ->label('HSL output')
                                ->hsl()
                                ->channelFields()
                                ->size('sm'),
                            FlexColorPickerField::make('flex_color_picker__readonly')
                                ->label('Read only')
                                ->rgb()
                                ->readOnly(),
                        ]),
                ]),
        ];
    }
}
