<?php

declare(strict_types=1);

// Uses the package's isolated Laravel/Livewire host; never boots the consumer app.
require __DIR__.'/location-v4-contract.php';

use Bjanczak\FilamentFlexFields\Support\ColorPicker\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexColorPickerPlayground;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;

$single = PickerConfiguration::resolve(['allowedFormats' => ['hsl']]);
locationCheck($single['format'] === 'hsl' && ! $single['showFormatSelector'], 'Single format must become the default and hide selection');
$opaque = PickerConfiguration::resolve(['format' => 'rgba', 'allowedFormats' => ['rgba', 'rgb']]);
locationCheck($opaque['format'] === 'rgb' && $opaque['allowedFormats'] === ['rgb'] && ! $opaque['showFormatSelector'], 'Opaque format normalization');
foreach ([[], ['css'], ['hex', 'rgb', 'rgba', 'hsl', 'hsla', 'hex'], [1], ['key' => 'hex']] as $invalid) {
    locationReject(fn () => PickerConfiguration::resolve(['allowedFormats' => $invalid]));
}
locationReject(fn () => PickerConfiguration::resolve(['format' => 'rgb', 'allowedFormats' => ['hex']]));
$colorHost = new class extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components((new FlexColorPickerPlayground)->components());
    }
};
$colorHost->setId('color-v4-probe');
$colorHost->form->fill((new FlexColorPickerPlayground)->defaultState());
$colorHtml = $colorHost->form->toHtml();
locationCheck(str_contains($colorHtml, 'fff-select-control') && str_contains($colorHtml, 'fffHeadlessSelectField'), 'Shared Select renderer absent');
locationCheck(! str_contains($colorHtml, '<select'), 'Native select introduced into Color Picker');
locationCheck(str_contains($colorHtml, 'sliderColorChannels()') && str_contains($colorHtml, 'fff-color-channel-suffix'), 'Sliders/channel views absent');
locationCheck(! str_contains($colorHtml, '@js('), 'Uncompiled directive');
$hexField = $colorHost->form->getComponent('flex_color_picker__hex_only');
locationCheck($hexField !== null, 'HEX-only field resolution');
$hexHtml = $hexField->toHtml();
locationCheck(! str_contains($hexHtml, 'fff-select-control'), 'Single-format field must omit the Select entirely');
$state = $colorHost->form->getState();
locationCheck($state['flex_color_picker__hsl'] === 'hsl(262, 83%, 58%)', 'Historical HSL state changed during rendering');
echo 'PASS: bounded allowed formats, opaque normalization, actual Color Picker schema rendering, shared Select, sliders layout and single-format DOM omission ('.strlen($colorHtml)." HTML bytes).\n";
