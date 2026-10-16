<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Filament\Schemas\Schema;

require __DIR__.'/schema-render-contract.php';
class LocationSelectRenderProbe extends V590SchemaHost
{
    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            AddressAutocompleteField::make('address')->default(['city' => 'Warsaw'])->editableAddress()->addressDetails(),
            MapPickerField::make('map')->default(['lat' => 52.2, 'lng' => 21.0, 'place_name' => 'Warsaw']),
        ]);
    }
}
$host = new LocationSelectRenderProbe;
$host->setId('location-select-render');
$host->form->fill();
$html = $host->form->toHtml();
foreach (['createLocationSelect', 'fff-select-headless-menu', 'fff-flex-text-input__wrapper'] as $required) {
    if (! str_contains($html, $required)) {
        throw new RuntimeException('Missing '.$required);
    }
}
foreach (['searchDropdown', 'onSearchKeydown', '<x-filament-flex-fields::'] as $retired) {
    if (str_contains($html, $retired)) {
        throw new RuntimeException('Retired/uncompiled renderer '.$retired);
    }
}
if (substr_count($html, 'createLocationSelect(') !== 2) {
    throw new RuntimeException('Independent search owner required per field.');
}
echo "PASS: real MapPicker/Address Filament SSR with two independent Select children, shared input/menu markup and no retired dropdown renderer.\n";

$document = new DOMDocument;
@$document->loadHTML($html);
$xpath = new DOMXPath($document);
$foundSdkRequirement = false;
foreach ($xpath->query('//*[@data-fff-asset-batch]') as $batch) {
    $requirements = json_decode($batch->getAttribute('data-fff-stylesheets'), true, flags: JSON_THROW_ON_ERROR);
    if (in_array(MapPickerField::make('probe')->getMapConfiguration()['assets']['stylesheet'], $requirements, true)) {
        $foundSdkRequirement = true;
    }
}
if (! $foundSdkRequirement) {
    throw new RuntimeException('SDK CSS must belong to the actual MapPicker consumer.');
}
if (str_contains($html, 'x-init="init()"')) {
    throw new RuntimeException('Location fields must use one automatic Alpine initialization.');
}
echo "PASS: external SDK CSS participates in per-instance asset ownership; no duplicate explicit initialization.\n";
