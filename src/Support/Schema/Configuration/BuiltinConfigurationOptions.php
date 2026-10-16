<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;

/** Closed stored-data vocabulary. PHP fluent callbacks belong to trusted application code. */
final class BuiltinConfigurationOptions
{
    /**
     * Defaults declare the normal vocabulary; these are additional adapter-supported options.
     * No reflection, filesystem discovery or caller-supplied PHP method names are involved.
     *
     * @return array<string, string>
     */
    public static function additional(FieldType $type): array
    {
        $options = match ($type) {
            FieldType::FlexTextInput, FieldType::Email, FieldType::Url,
            FieldType::Integer, FieldType::Decimal => [
                'copyable' => 'boolean', 'copy_message' => 'text', 'copy_message_duration' => 'integer',
            ],
            FieldType::Country => ['locale' => 'text'],
            FieldType::FontSelect => ['rounding' => 'text'],
            FieldType::PriceRange => ['locale' => 'text', 'rounding' => 'text', 'focus_outline' => 'boolean', 'read_only' => 'boolean'],
            FieldType::Timezone => ['locale' => 'text', 'include_utc' => 'boolean'],
            FieldType::ChoiceCards, FieldType::ChoiceCheckboxCards => ['columns' => 'integer'],
            FieldType::ImageChoiceCards => ['grid_columns' => 'integer'],
            FieldType::FlexChecklist, FieldType::FlexRadiolist => ['desc' => 'array'],
            FieldType::UserSelect => [
                'model' => 'text', 'size' => 'text', 'variant' => 'text', 'chip_color' => 'text',
                'keep_selected_options_in_dropdown' => 'boolean', 'color' => 'text', 'rich_options' => 'boolean',
                'option_view' => 'text', 'option_trigger_view' => 'text', 'rich_list_trigger_display' => 'boolean',
                'option_layout' => 'text', 'inline_search' => 'boolean', 'native' => 'boolean',
                'clearable' => 'boolean', 'dropdown_align' => 'text', 'chevron_icon' => 'text',
                'clear_icon' => 'text', 'disabled' => 'boolean',
            ],
            FieldType::TodoList => [
                'celebration_fullscreen' => 'boolean', 'check_sound' => 'text', 'create_label' => 'text',
                'create_placeholder' => 'text', 'search_prompt' => 'text',
                'infinite_scroll' => 'toggle-or-count', 'remote_loader' => 'text',
            ],
            FieldType::Tags => [
                'spatie_tag_locale' => 'text', 'spatie_tag_fallback_locale' => 'text',
                'spatie_tag_search_limit' => 'integer!',
            ],
            FieldType::Date, FieldType::DateTime, FieldType::DateRange,
            FieldType::Month, FieldType::Year, FieldType::Time, FieldType::TimeRange, FieldType::Duration => [
                'highlight_today' => 'boolean', 'show_calendar_button' => 'boolean', 'focus_outline' => 'boolean',
                'is_invalid' => 'boolean', 'rounding' => 'text', 'calendar_system' => 'text',
                'segment_invalid_message' => 'text', 'min_date' => 'text', 'max_date' => 'text',
                'week_starts_on' => 'integer!', 'allowed_times' => 'array', 'clearable' => 'boolean',
                'editable' => 'boolean', 'second_step' => 'integer!', 'default_time' => 'text!',
                'inline' => 'boolean', 'fixed_weeks' => 'boolean', 'outside_days' => 'boolean',
                'disabled_dates' => 'array!', 'disabled_weekdays' => 'array!',
                'calendar_months' => 'calendar-count!', 'range_presets' => 'array', 'duration_step' => 'integer!',
            ],
            FieldType::File, FieldType::Image => [
                'upload_layout' => 'text', 'avatar_size' => 'text', 'grid_columns' => 'integer',
                'instant_upload' => 'boolean', 'compact_header' => 'boolean', 'queue_image_conversions' => 'boolean',
                'image_conversion_queue' => 'array',
            ],
            FieldType::VoiceNote => [
                'storage_driver' => 'text', 'use_spatie_media_library' => 'boolean', 'media_collection' => 'text',
                'audio_bits_per_second' => 'integer!', 'confirm_deletion' => 'boolean', 'store_waveform_in' => 'text',
            ],
            default => [],
        };

        return ['width' => 'text', 'encrypted' => 'boolean', 'min_length' => 'integer', 'max_length' => 'integer',
            'formula' => 'text', 'calculated' => 'text'] + $options;
    }

    /**
     * Public preflight for application-owned saved-schema inventories; never silently drops settings.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function unsupportedKeys(FieldType $type, array $defaults, array $config): array
    {
        $declared = $defaults + self::additional($type);
        $unknown = [];
        foreach (array_keys($config) as $key) {
            if (! array_key_exists($key, $declared)) {
                $unknown[] = (string) $key;
            }
        }

        return $unknown;
    }

    /** @param array<string, mixed> $config */
    public static function assertAdditionalShapes(FieldType $type, array $config): void
    {
        foreach (self::additional($type) as $key => $shape) {
            if (! array_key_exists($key, $config)) {
                continue;
            }
            if (str_ends_with($shape, '!')) {
                ConfigurationRules::nonNull($config, $key);
                $shape = substr($shape, 0, -1);
            }
            match ($shape) {
                'boolean' => ConfigurationRules::boolean($config, $key),
                'integer' => ConfigurationRules::integer($config, $key),
                'text' => ConfigurationRules::text($config, $key),
                'array' => ConfigurationRules::array($config, $key),
                'calendar-count' => ConfigurationRules::choice($config, $key, [1, 2, 'auto']),
                'toggle-or-count' => ConfigurationRules::toggleOrCount($config, $key),
                default => throw new \LogicException('Undeclared built-in configuration shape.'),
            };
        }
    }
}
