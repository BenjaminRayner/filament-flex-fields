<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Support\Values\FieldStoragePath;

/** Validates adapter-consumed records before normalizers coerce or discard their members. */
final class StructuredConfigurationContract
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config): void
    {
        if ($type->supportsUserDefinedOptions() && array_key_exists('options', $config)) {
            self::options($config['options'], 'options');
        }
        if ($type === FieldType::FlexTextarea) {
            self::textarea($config);
        }
        if ($type === FieldType::MatrixChoice) {
            self::matrix($config);
        }
        if (in_array($type, [FieldType::File, FieldType::Image], true) && isset($config['custom_properties'])) {
            self::record($config['custom_properties'], 'custom_properties');
        }
        if ($type === FieldType::Tags) {
            ConfigurationRules::integer($config, 'spatie_tag_search_limit', 1, 100);
            ConfigurationRules::identifiers($config, 'split_keys', null, 64);
            self::options($config['suggestions'] ?? null, 'suggestions');
        }
        if ($type === FieldType::Audio) {
            foreach (ConfigurationRules::items($config, 'waveform') ?? [] as $peak) {
                if (! is_int($peak) && ! is_float($peak)) {
                    ConfigurationRules::fail('waveform', 'requires finite numeric peaks');
                }
                ConfigurationRules::number(['peak' => $peak], 'peak');
            }
        }
    }

    private static function options(mixed $options, string $key): void
    {
        if ($options === null) {
            return;
        }
        if (! is_array($options) || count($options) > 4096) {
            ConfigurationRules::fail($key, 'requires a bounded option list or map');
        }
        $seen = [];
        foreach ($options as $option) {
            if (is_string($option) || is_int($option)) {
                continue;
            }
            $row = self::record($option, $key);
            foreach (['value', 'label', 'description', 'desc', 'icon', 'image'] as $member) {
                if ($member === 'value' && array_key_exists($member, $row)) {
                    if (! is_string($row[$member]) && ! is_int($row[$member])) {
                        ConfigurationRules::fail($key.'.value', 'requires a literal string or integer');
                    }
                    $identity = (string) $row[$member];
                    if (isset($seen[$identity])) {
                        ConfigurationRules::fail($key.'.value', 'contains duplicate option identities');
                    }
                    $seen[$identity] = true;
                } else {
                    ConfigurationRules::text($row, $member);
                }
            }
            foreach (['disabled', 'required'] as $member) {
                ConfigurationRules::boolean($row, $member);
            }
            foreach (['min_selections', 'max_selections'] as $member) {
                ConfigurationRules::integer($row, $member);
            }
            ConfigurationRules::ordered($row, 'min_selections', 'max_selections');
            if (array_is_list($options) && ! isset($row['label']) && ! isset($row['value'])) {
                ConfigurationRules::fail($key, 'requires named option records');
            }
        }
    }

    /** @param array<string, mixed> $config */
    private static function textarea(array $config): void
    {
        $selects = ConfigurationRules::items($config, 'toolbar_selects', 32) ?? [];
        $singular = $config['toolbar_select'] ?? [];
        if ($singular !== []) {
            if (! is_array($singular)) {
                ConfigurationRules::fail('toolbar_select', 'requires a record or record list');
            }
            $selects = array_merge($selects, array_is_list($singular) ? $singular : [$singular]);
        }
        if (count($selects) > 32) {
            ConfigurationRules::fail('toolbar_selects', 'exceeds the toolbar control limit');
        }
        foreach ($selects as $raw) {
            $select = self::record($raw, 'toolbar_select', ['state_path', 'statePath', 'options', 'icon', 'placeholder']);
            foreach (['state_path', 'statePath'] as $key) {
                ConfigurationRules::text($select, $key, 1024);
                if (isset($select[$key])) {
                    FieldStoragePath::assert($select[$key]);
                }
            }
            if (! isset($select['state_path']) && ! isset($select['statePath'])) {
                ConfigurationRules::fail('toolbar_select.state_path', 'requires a literal state path');
            }
            if (isset($select['state_path'], $select['statePath']) && $select['state_path'] !== $select['statePath']) {
                ConfigurationRules::fail('toolbar_select.state_path', 'contains conflicting aliases');
            }
            self::options($select['options'] ?? [], 'toolbar_select.options');
            ConfigurationRules::text($select, 'icon', 512);
            ConfigurationRules::text($select, 'placeholder', 1024);
        }
        if (($config['submit_action'] ?? []) !== []) {
            $action = self::record($config['submit_action'], 'submit_action', ['name', 'label', 'icon']);
            ConfigurationRules::text($action, 'name', 96, '/^[a-zA-Z][a-zA-Z0-9_-]*$/D');
            ConfigurationRules::text($action, 'label', 1024);
            ConfigurationRules::text($action, 'icon', 512);
        }
    }

    /** @param array<string, mixed> $config */
    private static function matrix(array $config): void
    {
        foreach (['rows', 'columns'] as $key) {
            self::options($config[$key] ?? [], $key);
        }
        foreach (['disable_cell_when', 'disable_row_when'] as $key) {
            foreach (ConfigurationRules::items($config, $key, 4096) ?? [] as $raw) {
                $keys = ['row', 'when_row', 'when_columns', 'when_column'];
                if ($key === 'disable_cell_when') {
                    $keys[] = 'column';
                }
                $rule = self::record($raw, $key, $keys);
                foreach (['row', 'when_row', ...($key === 'disable_cell_when' ? ['column'] : [])] as $member) {
                    ConfigurationRules::text($rule, $member, 512);
                    if (! is_string($rule[$member] ?? null) || trim($rule[$member]) === '') {
                        ConfigurationRules::fail($key.'.'.$member, 'requires a literal choice identity');
                    }
                }
                if (array_key_exists('when_columns', $rule) && array_key_exists('when_column', $rule)) {
                    ConfigurationRules::fail($key, 'must use one conditional column representation');
                }
                $columns = $rule['when_columns'] ?? $rule['when_column'] ?? null;
                if (! is_string($columns) && ! is_array($columns)) {
                    ConfigurationRules::fail($key, 'requires conditional column identities');
                }
                ConfigurationRules::identifiers(['columns' => is_string($columns) ? [$columns] : $columns], 'columns');
            }
        }
        ConfigurationRules::array($config, 'disabled_cells');
        foreach ($config['disabled_cells'] ?? [] as $columns) {
            ConfigurationRules::identifiers(['columns' => $columns], 'columns');
        }
    }

    /** @param list<string>|null $keys
     * @return array<string, mixed>
     */
    private static function record(mixed $value, string $key, ?array $keys = null): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))
            || ($keys !== null && array_diff(array_keys($value), $keys) !== [])) {
            ConfigurationRules::fail($key, 'requires a declared named record');
        }

        $record = [];
        foreach ($value as $name => $member) {
            if (! is_string($name)) {
                ConfigurationRules::fail($key, 'requires named record members');
            }
            $record[$name] = $member;
        }

        return $record;
    }
}
