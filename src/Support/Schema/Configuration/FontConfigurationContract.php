<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators\FontSelectFieldConfigurator;

final class FontConfigurationContract
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config): void
    {
        if ($type !== FieldType::FontSelect) {
            return;
        }
        ConfigurationRules::choice($config, 'variant', ['bordered', 'secondary', 'flat', 'faded', 'soft', 'underlined', 'item-card']);
        ConfigurationRules::choice($config, 'rounding', ['md', 'lg', 'xl', 'native', 'full']);
        ConfigurationRules::integer($config, 'per_page', 1, 96);
        ConfigurationRules::integer($config, 'preview_weight', 1, 1000);
        ConfigurationRules::choice($config, 'preview_style', ['normal', 'italic']);
        ConfigurationRules::boolean($config, 'preview_selected_font');
        ConfigurationRules::choice($config, 'sort_fonts', ['popularity', 'alpha']);
        foreach (['providers', 'except_fonts', 'categories', 'subsets', 'local_fonts', 'category_labels', 'variable_only', 'category_tabs', 'per_page', 'preview_weight', 'preview_style', 'sort_fonts'] as $key) {
            ConfigurationRules::nonNull($config, $key);
        }
        try {
            $field = (new FontSelectFieldConfigurator)->configure(FontSelect::make('font_configuration'), $config);
            assert($field instanceof FontSelect);
            $field->getVariant();
            $field->getFontPreviewPolicy();
            $field->getFontCategoryTabs();
            $field->getAllowedFontFamilies();
        } catch (\TypeError $error) {
            throw new \InvalidArgumentException('Invalid font configuration shape.', previous: $error);
        }
    }
}
