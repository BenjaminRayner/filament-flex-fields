<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaPayloadContract;

/** Default declarations constrain shape; nullable and polymorphic options stay explicit. */
final class DeclaredConfigurationShape
{
    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $config
     */
    public static function assert(FieldType $type, array $defaults, array $config): void
    {
        SchemaPayloadContract::budget()->assert($config);
        if ($config !== [] && array_is_list($config)) {
            ConfigurationRules::fail('data', 'must be a named map');
        }
        // Validate names before reporting them: errors must not echo an unbounded forged key.
        foreach ($config as $key => $value) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,95}$/D', $key) !== 1) {
                ConfigurationRules::fail('keys', 'must be literal bounded setting names');
            }
        }
        $unknown = BuiltinConfigurationOptions::unsupportedKeys($type, $defaults, $config);
        if ($unknown !== []) {
            ConfigurationRules::fail($unknown[0], 'is not declared for '.$type->value);
        }
        BuiltinConfigurationOptions::assertAdditionalShapes($type, $config);
        foreach ($config as $key => $value) {
            if ($type === FieldType::TodoList && $key === 'celebration' && ($value === false || $value === null)) {
                continue;
            }
            if ($type === FieldType::TodoList && in_array($key, ['paginated', 'infinite_scroll'], true)) {
                ConfigurationRules::toggleOrCount($config, $key);

                continue;
            }
            if ($key === 'scoped_directory') {
                if (! is_bool($value)) {
                    ConfigurationRules::text($config, $key, 1024);
                    if ($value === null || $value === '') {
                        ConfigurationRules::fail($key, 'must be a boolean or a nonempty directory');
                    }
                }

                continue;
            }
            if ($type === FieldType::FontSelect && $key === 'category_tabs') {
                if (! is_bool($value) && ! is_array($value)) {
                    ConfigurationRules::fail($key, 'must be a boolean or category list');
                }

                continue;
            }
            if ($type === FieldType::FontSelect && in_array($key, ['fonts', 'preview_text'], true)) {
                continue;
            }
            $default = $defaults[$key] ?? null;
            if ($default === null && array_key_exists($key, $defaults)) {
                NullableConfigurationShape::assert($type, $config, $key);
            }
            if ($key === 'tooltips' && in_array($type, [FieldType::Color, FieldType::ColorPresets], true) && is_array($value)) {
                continue;
            }
            if ($type === FieldType::BubbleChoice && in_array($key, ['bubble_size', 'min_size', 'gutter', 'fringe_width', 'y_radius', 'x_radius', 'corner_radius', 'gravitation'], true)) {
                ConfigurationRules::number($config, $key, 0);

                continue;
            }
            if (is_bool($default) || $key === 'encrypted') {
                ConfigurationRules::boolean($config, $key);
            } elseif (is_array($default) && $value !== null && ! is_array($value)) {
                ConfigurationRules::fail($key, 'must be an array or null');
            } elseif (is_string($default)) {
                ConfigurationRules::text($config, $key);
            } elseif (is_int($default) && ! in_array($key, ['min', 'max', 'step'], true)) {
                ConfigurationRules::integer($config, $key);
            } elseif (is_float($default)) {
                ConfigurationRules::number($config, $key);
            }
        }
        if (isset($config['size']) && (! is_string($config['size']) || ControlSize::tryFrom($config['size']) === null)) {
            ConfigurationRules::fail('size', 'must be sm, md or lg');
        }
    }
}
