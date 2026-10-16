<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Enums\GeocodingSearchScope;
use Bjanczak\FilamentFlexFields\Enums\MapboxSearchType;

final class GeographyConfigurationContract
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config): void
    {
        if (in_array($type, [FieldType::Phone, FieldType::Country, FieldType::MapPicker, FieldType::AddressAutocomplete], true)) {
            foreach (['countries', 'except_countries'] as $key) {
                ConfigurationRules::identifiers($config, $key, null, 256, '/^[A-Za-z]{2}$/D');
            }
            ConfigurationRules::text($config, 'default_country', 2, '/^[A-Za-z]{2}$/D');
        }
        if ($type === FieldType::Country) {
            ConfigurationRules::identifiers($config, 'additional_regions', null, 6, '/^[A-Za-z]{2}$/D');
        }
        if ($type === FieldType::Timezone) {
            TemporalConfigurationContract::timezone($config, 'default_timezone');
            foreach (['timezones', 'except_timezones'] as $key) {
                foreach (ConfigurationRules::items($config, $key, 1024) ?? [] as $timezone) {
                    TemporalConfigurationContract::timezone(['timezone' => $timezone], 'timezone');
                    if ($timezone === null) {
                        ConfigurationRules::fail($key, 'requires timezone identifiers');
                    }
                }
            }
        }
        if (in_array($type, [FieldType::MapPicker, FieldType::AddressAutocomplete], true)) {
            ConfigurationRules::choice($config, 'search_scope', GeocodingSearchScope::values());
            ConfigurationRules::choice($config, 'search_area', ['global', 'countries']);
            ConfigurationRules::choice($config, 'language_mode', ['auto', 'manual']);
            ConfigurationRules::choice($config, 'store_format', ['structured', 'string']);
            ConfigurationRules::identifiers($config, 'search_types', array_column(MapboxSearchType::cases(), 'value'), 32);
            ConfigurationRules::identifiers($config, 'fields', null, 64, '/^[a-z][a-z0-9_]{0,95}$/D');
            ConfigurationRules::identifiers($config, 'required_fields', null, 64, '/^[a-z][a-z0-9_]{0,95}$/D');
            ConfigurationRules::integer($config, 'min_search_length', 0, 512);
            ConfigurationRules::integer($config, 'search_debounce', 0, 60000);
        }
        if ($type === FieldType::MapPicker) {
            ConfigurationRules::number($config, 'default_zoom', 0, 24);
            if (isset($config['default_center'])) {
                $center = ConfigurationRules::items($config, 'default_center', 2);
                if ($center === null || count($center) !== 2 || $center[0] === null || $center[1] === null) {
                    ConfigurationRules::fail('default_center', 'requires latitude and longitude');
                }
                ConfigurationRules::number(['latitude' => $center[0]], 'latitude', -90, 90);
                ConfigurationRules::number(['longitude' => $center[1]], 'longitude', -180, 180);
            }
        }
    }
}
