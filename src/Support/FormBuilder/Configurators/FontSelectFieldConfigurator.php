<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Contracts\FieldConfigurator;
use Filament\Schemas\Components\Component;

final class FontSelectFieldConfigurator implements FieldConfigurator
{
    public function configure(Component $field, array $config): Component
    {
        assert($field instanceof FontSelect);
        $field->size($config['size'] ?? 'md')->variant($config['variant'] ?? 'bordered');
        foreach (['providers', 'fonts', 'except_fonts' => 'exceptFonts', 'categories', 'subsets', 'local_fonts' => 'localFonts',
            'category_tabs' => 'categoryTabs', 'category_labels' => 'categoryLabels', 'variable_only' => 'variableOnly',
            'per_page' => 'perPage', 'preview_text' => 'previewText', 'preview_weight' => 'previewWeight',
            'preview_style' => 'previewStyle', 'preview_selected_font' => 'previewSelectedFont', 'sort_fonts' => 'sortFonts'] as $key => $method) {
            $key = is_int($key) ? $method : $key;
            if (array_key_exists($key, $config)) {
                $field->{$method}($config[$key]);
            }
        }
        if (isset($config['rounding'])) {
            $field->rounding($config['rounding']);
        }

        return $field;
    }
}
