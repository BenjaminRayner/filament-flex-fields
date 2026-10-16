<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Enums\FlexFieldWidth;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\TextInput\TextInputOptions;

/** One admission boundary for stored schema, exchange and dynamic field construction. */
final class BuiltinConfigurationContract
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config): void
    {
        $defaults = $type->defaultConfig();
        DeclaredConfigurationShape::assert($type, $defaults, $config);
        if (array_key_exists('counter_mode', $defaults)) {
            TextInputOptions::resolve(TextInputOptions::schemaOverrides($config));
        }
        NumericConfigurationContract::assert($type, $defaults, $config);
        TemporalConfigurationContract::assert($type, $config);
        ChoiceConfigurationContract::assert($type, $config);
        MediaConfigurationContract::assert($type, $config);
        GeographyConfigurationContract::assert($type, $config);
        FontConfigurationContract::assert($type, $config);
        StructuredConfigurationContract::assert($type, $config);
        StoredConfigurationReferences::assert($type, $config);
        foreach (['formula', 'calculated'] as $key) {
            if (isset($config[$key]) && trim($config[$key]) !== '' && FormulaEngine::validate($config[$key]) !== []) {
                ConfigurationRules::fail($key, 'requires a valid bounded formula expression');
            }
        }
        if (array_key_exists('formula', $config) && array_key_exists('calculated', $config) && $config['formula'] !== $config['calculated']) {
            ConfigurationRules::fail('formula', 'contains conflicting aliases');
        }
        if (isset($config['width']) && $config['width'] !== '' && (! is_string($config['width']) || FlexFieldWidth::tryFrom($config['width']) === null)) {
            ConfigurationRules::fail('width', 'must be a supported field width');
        }
    }
}
