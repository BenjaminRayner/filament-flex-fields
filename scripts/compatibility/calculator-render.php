<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CalculatorField;
use Filament\Schemas\Schema;

require __DIR__.'/schema-render-contract.php';
class CalculatorRenderProbe extends V590SchemaHost
{
    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            CalculatorField::make('amount')->default(12.5)->decimalPlaces(2)->minValue(0)->maxValue(100),
            CalculatorField::make('quantity')->integer()->default(3),
            CalculatorField::make('locked')->disabled(),
        ]);
    }
}
$host = new CalculatorRenderProbe;
$host->setId('calculator-render');
$host->form->fill();
$html = $host->form->toHtml();
if (substr_count($html, 'data-fff-text-input-options=') !== 3) {
    throw new RuntimeException('Each calculator must render the actual FlexTextInput.');
}
if (substr_count($html, 'data-fff-calculator-panel role=') !== 3) {
    throw new RuntimeException('Expected three inert panel templates.');
}
if (str_contains($html, 'calculatorFieldFormComponent({') || str_contains($html, 'numberInput')) {
    throw new RuntimeException('Retired input renderer still present.');
}
if (! str_contains($html, 'fff-calculator-open') || ! str_contains($html, 'data-fff-calculator-trigger')) {
    throw new RuntimeException('Missing local calculator action.');
}
$document = new DOMDocument;
@$document->loadHTML($html);
$xpath = new DOMXPath($document);
$buttons = $xpath->query('//button[@data-fff-calculator-trigger]');
if ($buttons->length !== 3) {
    throw new RuntimeException('Missing calculator toolbar buttons.');
}
foreach ($buttons as $button) {
    if (! str_contains($button->getAttribute('class'), 'fi-icon-btn')) {
        throw new RuntimeException('Calculator action must be an icon button.');
    }
    if ($button->getAttribute('x-on:click') !== "\$dispatch('fff-calculator-open', { anchor: \$event.currentTarget })") {
        throw new RuntimeException('Calculator click expression was truncated by HTML quoting.');
    }
}
echo "PASS: real calculator SSR uses native numeric FlexTextInput and local action for every variant.\n";
