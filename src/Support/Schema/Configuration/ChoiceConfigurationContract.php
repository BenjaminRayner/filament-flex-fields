<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Support\Choice\RichOptionSchemaV2;
use Bjanczak\FilamentFlexFields\Support\ColorPicker\PickerConfiguration;

final class ChoiceConfigurationContract
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config): void
    {
        if ($type->supportsUserDefinedOptions()) {
            foreach (['disabled_options', 'disabled_rows', 'required_rows', 'split_keys'] as $key) {
                ConfigurationRules::identifiers($config, $key);
            }
        }
        if ($type === FieldType::DualListbox) {
            ConfigurationRules::integer($config, 'virtual_threshold', 1, 50000);
        }
        if (in_array($type, [FieldType::ChoiceCards, FieldType::ChoiceCheckboxCards, FieldType::ImageChoiceCards,
            FieldType::FlexChecklist, FieldType::FlexRadiolist, FieldType::DualListbox, FieldType::MatrixChoice], true)) {
            $keys = $type === FieldType::MatrixChoice ? ['rows', 'columns'] : ['options'];
            foreach ($keys as $key) {
                if (isset($config[$key]) && ! RichOptionSchemaV2::validate($config[$key])) {
                    ConfigurationRules::fail($key, 'requires valid literal choice records');
                }
                foreach ($config[$key] ?? [] as $option) {
                    if (is_array($option)) {
                        if (array_key_exists('value', $option) && ! is_string($option['value']) && ! is_int($option['value'])) {
                            ConfigurationRules::fail($key, 'requires literal string or integer option values');
                        }
                        ConfigurationRules::text($option, 'label');
                    }
                }
            }
        }
        if (in_array($type, [FieldType::ChoiceCards, FieldType::ChoiceCheckboxCards], true)) {
            ConfigurationRules::choice($config, 'layout', ['stack', 'grid']);
            ConfigurationRules::integer($config, 'grid_columns', 1, 12);
        }
        if ($type === FieldType::ImageChoiceCards) {
            ConfigurationRules::integer($config, 'columns', 1, 12);
            ConfigurationRules::choice($config, 'image_fit', ['cover', 'contain', 'fill', 'none', 'scale-down']);
        }
        if ($type === FieldType::MatrixChoice) {
            ConfigurationRules::choice($config, 'mode', ['radio', 'checkbox']);
        }
        if ($type === FieldType::IconPicker) {
            ConfigurationRules::integer($config, 'grid_columns', 1, 32);
            ConfigurationRules::integer($config, 'per_page', 1, 1000);
            ConfigurationRules::integer($config, 'limit_per_set', 1, 10000);
            ConfigurationRules::choice($config, 'layout', ['grid', 'list']);
            ConfigurationRules::choice($config, 'search_results_layout', ['grid', 'list']);
        }
        if ($type === FieldType::VerificationCode) {
            ConfigurationRules::integer($config, 'length', 1, 128);
            $groups = ConfigurationRules::items($config, 'groups', 128);
            foreach ($groups ?? [] as $group) {
                ConfigurationRules::integer(['group' => $group], 'group', 1, 128);
                if ($group === null) {
                    ConfigurationRules::fail('groups', 'must contain positive group lengths');
                }
            }
            if ($groups !== null && $groups !== [] && array_sum($groups) !== (int) ($config['length'] ?? 6)) {
                ConfigurationRules::fail('groups', 'must sum to the configured length');
            }
        }
        if ($type === FieldType::FlexColorPicker) {
            $options = [];
            foreach (['layout' => 'layout', 'format' => 'format', 'alpha' => 'alphaEnabled',
                'eyedropper' => 'eyedropperEnabled', 'grid_columns' => 'gridColumns', 'grid_rows' => 'gridRows', 'grid_colors' => 'gridColors'] as $key => $option) {
                if (array_key_exists($key, $config)) {
                    $options[$option] = $config[$key];
                }
            }
            PickerConfiguration::resolve($options);
        }
    }
}
