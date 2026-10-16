<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;

final class NumericConfigurationContract
{
    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $config
     */
    public static function assert(FieldType $type, array $defaults, array $config): void
    {
        foreach (['min', 'max', 'step'] as $key) {
            if (array_key_exists($key, $defaults)) {
                ConfigurationRules::number($config, $key);
            }
        }
        $effective = array_replace($defaults, $config);
        if (array_key_exists('step', $defaults) && isset($effective['step']) && (float) $effective['step'] <= 0) {
            ConfigurationRules::fail('step', 'must be positive');
        }
        if (array_key_exists('min', $defaults) && array_key_exists('max', $defaults)) {
            ConfigurationRules::ordered($effective, 'min', 'max');
            if (isset($effective['min'], $effective['max']) && ! is_finite((float) $effective['max'] - (float) $effective['min'])) {
                ConfigurationRules::fail('max', 'produces a nonfinite numeric span');
            }
        }
        foreach (['min_length', 'max_length', 'min_items', 'max_items', 'exact_items', 'min_selections', 'max_selections', 'exact_selections', 'min_done', 'max_done', 'exact_done', 'max_tags'] as $key) {
            ConfigurationRules::integer($config, $key);
        }
        foreach ([['min_length', 'max_length'], ['min_items', 'max_items', 'exact_items'],
            ['min_selections', 'max_selections', 'exact_selections'], ['min_done', 'max_done', 'exact_done']] as $keys) {
            ConfigurationRules::ordered($effective, ...$keys);
        }
        if (in_array($type, [FieldType::FlexSlider, FieldType::PriceRange], true)) {
            ConfigurationRules::integer($config, 'decimal_places', 0, 10);
        }
        if ($type === FieldType::FlexSlider) {
            ConfigurationRules::choice($config, 'thumb_style', ['pill', 'circle']);
            ConfigurationRules::choice($config, 'value_placement', ['header', 'footer']);
            ConfigurationRules::boolean($config, 'elastic');
            ConfigurationRules::boolean($config, 'tooltips');
            foreach (['icon_button', 'icon_button_active_icon', 'icon_button_label', 'icon_button_active_label'] as $key) {
                ConfigurationRules::text($config, $key, 512);
            }
            ConfigurationRules::choice($config, 'icon_button_position', ['start', 'end']);
            foreach (['icon_button_value', 'icon_button_restore_value'] as $key) {
                ConfigurationRules::number($config, $key, (float) $effective['min'], (float) $effective['max']);
            }
            ConfigurationRules::boolean($config, 'haptics');
            ConfigurationRules::number($config, 'stretch_limit', 1, 80);
            ConfigurationRules::number($config, 'detent_distance', 0, 32);
            ConfigurationRules::number($config, 'large_step', PHP_FLOAT_MIN, 9007199254740991);
            foreach (ConfigurationRules::items($config, 'marks', 128) ?? [] as $mark) {
                $value = is_array($mark) ? ($mark['value'] ?? null) : $mark;
                ConfigurationRules::number(['mark' => $value], 'mark', (float) $effective['min'], (float) $effective['max']);
                if ($value === null) {
                    ConfigurationRules::fail('marks', 'requires a value for every mark');
                }
                if (is_array($mark)) {
                    ConfigurationRules::text($mark, 'label', 512);
                }
            }
            ConfigurationRules::text($config, 'start_icon', 512);
            ConfigurationRules::text($config, 'end_icon', 512);
        }
        if ($type === FieldType::PriceRange) {
            foreach (ConfigurationRules::items($config, 'histogram', 1024) ?? [] as $bar) {
                ConfigurationRules::number(['bar' => $bar], 'bar');
                if ($bar === null) {
                    ConfigurationRules::fail('histogram', 'requires finite bar values');
                }
            }
        }
        if ($type === FieldType::Rating) {
            ConfigurationRules::integer($config, 'max', 1, 100);
        }
        if ($type === FieldType::Nps) {
            ConfigurationRules::integer($config, 'scale_max', 1, 100);
        }
        if ($type === FieldType::TrafficSplit) {
            ConfigurationRules::integer($config, 'segment_count', 2, 100);
            ConfigurationRules::number($config, 'min_weight', 0, 100);
        }
    }
}
