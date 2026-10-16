<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PriceRangeField;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Contracts\FieldConfigurator;
use Filament\Schemas\Components\Component;

final class PriceRangeFieldConfigurator implements FieldConfigurator
{
    public function configure(Component $field, array $config): Component
    {
        assert($field instanceof PriceRangeField);

        return $this->configurePriceRangeField($field, $config);
    }

    /** @param array<string, mixed> $config */
    public function configurePriceRangeField(PriceRangeField $field, array $config): PriceRangeField
    {
        $field->min($config['min'] ?? 0)->max($config['max'] ?? 1000)->step($config['step'] ?? 1)
            ->size($config['size'] ?? config('filament-flex-fields.ui.price_range_size', 'md'))
            ->variant($config['variant'] ?? config('filament-flex-fields.ui.price_range_variant', 'primary'));

        foreach (['prefix' => 'prefix', 'histogram' => 'histogram', 'integer' => 'integer',
            'decimal_places' => 'decimalPlaces', 'show_inputs' => 'showInputs',
            'min_input_label' => 'minInputLabel', 'max_input_label' => 'maxInputLabel',
            'locale' => 'locale', 'rounding' => 'rounding', 'focus_outline' => 'focusOutline',
            'read_only' => 'readOnly'] as $key => $method) {
            if (array_key_exists($key, $config)) {
                $field->{$method}($config[$key]);
            }
        }
        $field->getRangeConstraints();

        return $field;
    }
}
