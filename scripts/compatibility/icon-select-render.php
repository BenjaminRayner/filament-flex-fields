<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Filament\Schemas\Schema;

require __DIR__.'/schema-render-contract.php';
class IconSelectRenderProbe extends V590SchemaHost
{
    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            IconPickerField::make('grid')->sets('hugeicons')->grid()->gridColumns(6),
            IconPickerField::make('list')->sets('hugeicons')->list(),
            IconPickerField::make('icons')->sets('hugeicons')->iconsOnly()->disabled(),
        ]);
    }
}
$host = new IconSelectRenderProbe;
$host->setId('icon-select-render');
$host->form->fill();
$html = $host->form->toHtml();
foreach (['fff-select-headless-menu', 'fff-icon-select__option', '--fff-icon-columns: 6'] as $required) {
    if (! str_contains($html, $required)) {
        throw new RuntimeException('Missing '.$required);
    }
}
foreach (['pickerPanel', 'iconVirtualWindow', 'togglePanel()', '<x-filament-flex-fields::'] as $retired) {
    if (str_contains($html, $retired)) {
        throw new RuntimeException('Retired/uncompiled renderer '.$retired);
    }
}
if (substr_count($html, 'iconPickerFieldFormComponent(') !== 3) {
    throw new RuntimeException('One independent owner per icon field required.');
}
echo "PASS: real IconPicker SSR composes the Select menu for list, grid, icons and disabled variants.\n";
