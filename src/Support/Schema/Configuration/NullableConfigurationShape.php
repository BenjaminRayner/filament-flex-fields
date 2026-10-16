<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;

/** Null defaults do not grant admission to arbitrary JSON structures. */
final class NullableConfigurationShape
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config, string $key): void
    {
        if ($type === FieldType::FlexSlider && in_array($key, ['icon_button_value', 'icon_button_restore_value', 'large_step'], true)) {
            ConfigurationRules::number($config, $key);

            return;
        }
        if ($key === 'min' || $key === 'max') {
            ConfigurationRules::number($config, $key);

            return;
        }
        if (in_array($key, ['min_value', 'max_value'], true) && $type === FieldType::Duration) {
            // The duration codec accepts both seconds and its literal clock representation.
            return;
        }
        if (in_array($key, ['soft_limit', 'counter_limit', 'reveal_timeout', 'min_length', 'max_length',
            'max_slug_length', 'limit_per_set', 'min_items', 'max_items', 'exact_items',
            'min_selections', 'max_selections', 'exact_selections', 'min_done', 'max_done', 'exact_done',
            'max_tags', 'min_files', 'max_files', 'max_total_size_kb', 'max_image_width', 'max_image_height',
            'max_links', 'view_box_width', 'view_box_height', 'decode_fps'], true)) {
            ConfigurationRules::integer($config, $key);

            return;
        }
        if (in_array($key, ['countries', 'allow_types', 'timezones', 'currencies', 'translatable_locales',
            'required_title_locales', 'days', 'workdays', 'grid_colors', 'waveform', 'formats'], true)) {
            ConfigurationRules::array($config, $key);

            return;
        }
        ConfigurationRules::text($config, $key);
    }
}
