<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexColorPickerField;

// Advanced layout: HSV area, hue, alpha and editable channels.
FlexColorPickerField::make('advanced')->label('Advanced layout')->alpha();

FlexColorPickerField::make('grid')->label('Grid layout')->layout('grid')->alpha();
FlexColorPickerField::make('secondary')->label('Secondary variant')->variant('secondary');

// Compact triggers; opaque icon mode also works in map zone controls.
FlexColorPickerField::make('icon')->label('Icon only · opaque')
    ->iconOnly()->alpha(false)->eyedropper(false)->showFields(false);
FlexColorPickerField::make('swatches')->label('Compact swatches')
    ->swatchOnly()->layout('swatches')->closeOnSelect();

// Shared Select changes the displayed channels; hsl() chooses the stored format.
FlexColorPickerField::make('sliders')->label('Sliders only · HSL / RGB')
    ->layout('sliders')->hsl()->allowedFormats(['hsl', 'rgb'])
    ->alpha()->eyedropper(false);

// A single permitted format removes the format selector entirely.
FlexColorPickerField::make('hex_only')->label('HEX only · no format selector')
    ->allowedFormats(['hex'])->eyedropper(false)->channelFields();

FlexColorPickerField::make('palette')->label('With swatches')
    ->showSwatches()->showFields(false)->eyedropper(false);
FlexColorPickerField::make('inline')->label('Inline channels and swatches')
    ->inline()->showSwatches()->alpha();

FlexColorPickerField::make('alpha')->label('RGBA output')->rgba()->alpha()->eyedropper(false);
FlexColorPickerField::make('hsl')->label('HSL output')->hsl()->channelFields()->size('sm');
FlexColorPickerField::make('readonly')->label('Read only')->rgb()->readOnly();
