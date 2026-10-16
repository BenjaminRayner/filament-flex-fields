<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schema\Admin;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Schema\Admin\Concerns\BuildsCommonAdminFields;
use Bjanczak\FilamentFlexFields\Filament\Schema\Admin\Concerns\InteractsWithFieldTypeAdminContext;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldTypeSettingsStorage;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

final class FieldTypeAutoAdminSchema
{
    use BuildsCommonAdminFields;
    use InteractsWithFieldTypeAdminContext;

    /**
     * @return list<string>
     */
    public static function configurableKeysFor(FieldType $type): array
    {
        $reserved = FlexFieldTypeSettingsStorage::reservedConfigKeys($type);

        return array_values(array_diff(array_keys($type->defaultConfig()), $reserved));
    }

    public static function hasConfigurableSettings(FieldType $type): bool
    {
        return self::configurableKeysFor($type) !== [];
    }

    /**
     * @return list<Component>
     */
    public static function schemaComponentsFor(FieldType $type): array
    {
        return self::schemaForType($type);
    }

    /**
     * @var array<string, array<string, string>>
     */
    private const ENUM_OPTIONS = [
        'size' => [
            'xs' => 'XS',
            'sm' => 'SM',
            'md' => 'MD',
            'lg' => 'LG',
            'xl' => 'XL',
        ],
        'variant' => [
            'default' => 'filament-flex-fields::schema-options.default',
            'primary' => 'filament-flex-fields::schema-options.primary',
            'bordered' => 'filament-flex-fields::schema-options.bordered',
            'midnight' => 'filament-flex-fields::schema-options.midnight',
        ],
        'input_variant' => [
            'default' => 'filament-flex-fields::schema-options.default',
            'primary' => 'filament-flex-fields::schema-options.primary',
            'bordered' => 'filament-flex-fields::schema-options.bordered',
        ],
        'layout' => [
            'row' => 'filament-flex-fields::schema-options.row',
            'card' => 'filament-flex-fields::schema-options.card',
            'stack' => 'filament-flex-fields::schema-options.stack',
            'grid' => 'filament-flex-fields::schema-options.grid',
            'input' => 'filament-flex-fields::schema-options.input',
            'map' => 'filament-flex-fields::schema-options.map',
            'simple' => 'filament-flex-fields::schema-options.simple',
            'advanced' => 'filament-flex-fields::schema-options.advanced',
        ],
        'indicator' => [
            'radio' => 'filament-flex-fields::schema-options.radio',
            'checkbox' => 'filament-flex-fields::schema-options.checkbox',
            'none' => 'filament-flex-fields::schema-options.none',
        ],
        'mode' => [
            'radio' => 'filament-flex-fields::schema-options.single_choice',
            'checkbox' => 'filament-flex-fields::schema-options.multiple_choice',
        ],
        'label_position' => [
            'start' => 'filament-flex-fields::schema-options.start',
            'end' => 'filament-flex-fields::schema-options.end',
        ],
        'image_fit' => [
            'cover' => 'filament-flex-fields::schema-options.cover',
            'contain' => 'filament-flex-fields::schema-options.contain',
        ],
        'rounding' => [
            'none' => 'filament-flex-fields::schema-options.none',
            'md' => 'MD',
            'lg' => 'LG',
            'full' => 'filament-flex-fields::schema-options.full',
        ],
        'granularity' => [
            'day' => 'filament-flex-fields::schema-options.day',
            'hour' => 'filament-flex-fields::schema-options.hour',
            'minute' => 'filament-flex-fields::schema-options.minute',
            'second' => 'filament-flex-fields::schema-options.second',
        ],
        'time_picker' => [
            'segmented' => 'filament-flex-fields::schema-options.segmented',
            'native' => 'filament-flex-fields::schema-options.native',
        ],
        'store_format' => [
            'structured' => 'filament-flex-fields::schema-options.structured',
            'string' => 'filament-flex-fields::schema-options.string',
        ],
        'format' => [
            'hex' => 'Hex',
            'rgb' => 'RGB',
            'hsl' => 'HSL',
        ],
        'allowed_characters' => [
            'numeric' => 'filament-flex-fields::schema-options.numeric',
            'alpha' => 'filament-flex-fields::schema-options.alpha',
            'alphanumeric' => 'filament-flex-fields::schema-options.alphanumeric',
        ],
        'mask_preset' => [
            '' => 'filament-flex-fields::schema-options.none',
            'phone' => 'filament-flex-fields::schema-options.phone',
            'custom' => 'filament-flex-fields::schema-options.custom',
        ],
        'search_scope' => [
            'all' => 'filament-flex-fields::schema-options.all',
            'country' => 'filament-flex-fields::schema-options.country',
            'city' => 'filament-flex-fields::schema-options.city',
        ],
        'search_area' => [
            'global' => 'filament-flex-fields::schema-options.global',
            'local' => 'filament-flex-fields::schema-options.local',
        ],
        'language_mode' => [
            'auto' => 'filament-flex-fields::schema-options.auto',
            'fixed' => 'filament-flex-fields::schema-options.fixed',
        ],
        'controls_layout' => [
            'compact' => 'filament-flex-fields::schema-options.compact',
            'expanded' => 'filament-flex-fields::schema-options.expanded',
        ],
        'camera_facing' => [
            'environment' => 'filament-flex-fields::schema-options.rear',
            'user' => 'filament-flex-fields::schema-options.front',
        ],
        'value_position' => [
            'start' => 'filament-flex-fields::schema-options.start',
            'end' => 'filament-flex-fields::schema-options.end',
        ],
        'color' => [
            'primary' => 'filament-flex-fields::schema-options.primary',
            'success' => 'filament-flex-fields::schema-options.success',
            'warning' => 'filament-flex-fields::schema-options.warning',
            'danger' => 'filament-flex-fields::schema-options.danger',
            'neutral' => 'filament-flex-fields::schema-options.neutral',
        ],
        'chip_color' => [
            'neutral' => 'filament-flex-fields::schema-options.neutral',
            'primary' => 'filament-flex-fields::schema-options.primary',
            'success' => 'filament-flex-fields::schema-options.success',
            'warning' => 'filament-flex-fields::schema-options.warning',
            'danger' => 'filament-flex-fields::schema-options.danger',
        ],
        'download_format' => [
            'svg' => 'SVG',
            'webp' => 'WebP',
        ],
        'month_display' => [
            'numeric' => 'filament-flex-fields::schema-options.numeric',
            'short' => 'filament-flex-fields::schema-options.short',
            'long' => 'filament-flex-fields::schema-options.long',
        ],
        'dropdown_align' => [
            'start' => 'filament-flex-fields::schema-options.start',
            'center' => 'filament-flex-fields::schema-options.center',
            'end' => 'filament-flex-fields::schema-options.end',
        ],
        'search_results_layout' => [
            'grid' => 'filament-flex-fields::schema-options.grid',
            'list' => 'filament-flex-fields::schema-options.list',
        ],
        'auto_submit_method' => [
            'livewire' => 'Livewire',
            'form' => 'filament-flex-fields::schema-options.form',
        ],
    ];

    /**
     * @var list<string>
     */
    private const TAGS_ARRAY_KEYS = [
        'colors',
        'countries',
        'except_countries',
        'timezones',
        'except_timezones',
        'fields',
        'required_fields',
        'platforms',
        'exclude_platforms',
        'formats',
        'disabled_options',
        'disabled_rows',
        'required_rows',
        'locked_days',
        'workdays',
        'days',
        'currencies',
        'grid_colors',
        'split_keys',
        'labels',
        'locked_segments',
        'search_types',
        'icons',
        'descriptions',
        'histogram',
        'accepted_types',
        'sets',
        'exclude_icons',
        'unavailable_dates',
        'playback_rates',
        'quality_options',
        'preview_thumbnails',
        'disabled_options',
    ];

    /**
     * @var list<string>
     */
    private const COLOR_KEYS = [
        'pen_color',
        'background_color',
        'suffix_icon_color',
        'on_color',
        'off_color',
        'fill_color',
    ];

    /**
     * @var list<string>
     */
    private const JSON_ARRAY_KEYS = [
        'disable_row_when',
        'column_icons',
        'options',
        'submit_action',
        'toolbar_select',
        'slug_unique_parameters',
        'custom_properties',
    ];

    /**
     * @return list<Component>
     */
    public static function components(): array
    {
        $components = [];

        foreach (FieldType::cases() as $type) {
            $schema = array_merge(
                self::schemaForType($type),
                self::passthroughTypeHelp($type),
            );

            if ($schema === []) {
                continue;
            }

            $fieldset = Fieldset::make('type_settings_'.$type->value)
                ->label(fn () => __('filament-flex-fields::default.schema.type_settings_for', [
                    'type' => $type->label(),
                ]))
                ->schema($schema)
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => self::selectedType($get) === $type);

            $components[] = $fieldset;
        }

        return $components;
    }

    /**
     * @return list<Component>
     */
    private static function schemaForType(FieldType $type): array
    {
        $defaults = $type->defaultConfig();
        $reserved = FlexFieldTypeSettingsStorage::reservedConfigKeys($type);
        $schema = [];

        foreach ($defaults as $key => $default) {
            if (in_array($key, $reserved, true)) {
                continue;
            }

            $schema[] = self::componentForKey($key, $default, $type);
        }

        return $schema;
    }

    private static function componentForKey(string $key, mixed $default, FieldType $type): Component
    {
        $path = self::settingsPath($key);
        $label = static function () use ($key): string {
            $translation = 'filament-flex-fields::default.schema.settings.'.$key;

            return app('translator')->has($translation)
                ? Translations::get($translation)
                : Str::headline(str_replace('_', ' ', $key));
        };

        if ($key === 'disable_cell_when') {
            return self::matrixDisableCellWhenRepeater($path);
        }

        if ($key === 'toolbar_selects') {
            return self::flexTextareaToolbarSelectsRepeater($path);
        }

        if ($key === 'options' && $type === FieldType::Nps) {
            return self::npsOptionsRepeater($path);
        }

        if (in_array($key, self::COLOR_KEYS, true)) {
            return ColorPicker::make($path)
                ->label($label)
                ->hexColor();
        }

        if (isset(self::ENUM_OPTIONS[$key])) {
            return Select::make($path)
                ->label($label)
                ->options(fn (): array => array_map(
                    static fn (string $label): string => str_starts_with($label, 'filament-flex-fields::') ? Translations::get($label) : $label,
                    self::ENUM_OPTIONS[$key],
                ))
                ->native(false)
                ->searchable();
        }

        if (in_array($key, self::TAGS_ARRAY_KEYS, true) || (is_array($default) && array_is_list($default) && ($default === [] || is_string($default[0] ?? null)))) {
            return TagsInput::make($path)
                ->label($label)
                ->placeholder($label);
        }

        if (in_array($key, self::JSON_ARRAY_KEYS, true) || (is_array($default) && ! array_is_list($default))) {
            return Textarea::make($path)
                ->label($label)
                ->rows(3)
                ->helperText(fn () => __('filament-flex-fields::default.schema.settings.structured_value_help'))
                ->formatStateUsing(function (mixed $state): ?string {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    if (is_array($state)) {
                        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: null;
                    }

                    return is_string($state) ? $state : null;
                })
                ->dehydrateStateUsing(function (mixed $state) use ($default): mixed {
                    if (! is_string($state) || trim($state) === '') {
                        return is_array($default) ? $default : null;
                    }

                    $decoded = json_decode($state, true);

                    return is_array($decoded) ? $decoded : $state;
                });
        }

        if (is_bool($default)) {
            return Toggle::make($path)
                ->label($label)
                ->inline(false);
        }

        if (is_int($default) || is_float($default)) {
            $input = TextInput::make($path)
                ->label($label)
                ->numeric();

            if ($key === 'min_items') {
                $input->helperText(fn () => __('filament-flex-fields::default.schema.settings.min_items_help'));
            }

            if ($key === 'max_items') {
                $input->helperText(fn () => __('filament-flex-fields::default.schema.settings.max_items_help'));
            }

            return $input;
        }

        if (is_array($default)) {
            return TagsInput::make($path)
                ->label($label);
        }

        return TextInput::make($path)
            ->label($label);
    }

    private static function matrixDisableCellWhenRepeater(string $path): Repeater
    {
        return Repeater::make($path)
            ->label(fn () => __('filament-flex-fields::default.schema.settings.disable_cell_when'))
            ->helperText(fn () => __('filament-flex-fields::default.schema.settings.disable_cell_when_help'))
            ->schema([
                TextInput::make('row')
                    ->label(fn () => __('filament-flex-fields::default.schema.settings.rule_row'))
                    ->required(),
                TextInput::make('column')
                    ->label(fn () => __('filament-flex-fields::default.schema.settings.rule_column')),
                TextInput::make('when_row')
                    ->label(fn () => __('filament-flex-fields::default.schema.settings.rule_when_row'))
                    ->required(),
                TextInput::make('when_columns')
                    ->label(fn () => __('filament-flex-fields::default.schema.settings.rule_when_columns'))
                    ->helperText(fn () => __('filament-flex-fields::default.schema.settings.rule_when_columns_help')),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->collapsible()
            ->columnSpanFull();
    }

    private static function flexTextareaToolbarSelectsRepeater(string $path): Repeater
    {
        return Repeater::make($path)
            ->label(fn () => __('filament-flex-fields::default.schema.settings.toolbar_selects'))
            ->helperText(fn () => __('filament-flex-fields::default.schema.settings.toolbar_selects_help'))
            ->schema([
                TextInput::make('state_path')
                    ->label(fn () => __('filament-flex-fields::default.schema.settings.toolbar_state_path'))
                    ->required(),
                KeyValue::make('options')
                    ->label(fn () => __('filament-flex-fields::default.schema.settings.toolbar_options'))
                    ->keyLabel(fn () => __('filament-flex-fields::default.schema.field_option_value'))
                    ->valueLabel(fn () => __('filament-flex-fields::default.schema.field_option_label'))
                    ->reorderable(),
                TextInput::make('icon')
                    ->label(fn () => __('filament-flex-fields::default.schema.settings.toolbar_icon'))
                    ->placeholder('hugeicons-sparkles'),
                TextInput::make('placeholder')
                    ->label(fn () => __('filament-flex-fields::default.schema.settings.toolbar_placeholder')),
            ])
            ->defaultItems(0)
            ->collapsible()
            ->columnSpanFull();
    }

    private static function npsOptionsRepeater(string $path): Repeater
    {
        return Repeater::make($path)
            ->label(fn () => __('filament-flex-fields::default.schema.settings.nps_options'))
            ->helperText(fn () => __('filament-flex-fields::default.schema.settings.nps_options_help'))
            ->schema([
                TextInput::make('value')
                    ->label(fn () => __('filament-flex-fields::default.schema.field_option_value'))
                    ->required(),
                TextInput::make('label')
                    ->label(fn () => __('filament-flex-fields::default.schema.field_option_label'))
                    ->required(),
            ])
            ->defaultItems(1)
            ->reorderable()
            ->collapsible()
            ->columnSpanFull();
    }

    /**
     * @return list<Component>
     */
    private static function passthroughTypeHelp(FieldType $type): array
    {
        return match ($type) {
            FieldType::KeyValue => [
                Placeholder::make('type_settings_key_value_help')
                    ->label('')
                    ->content(fn () => __('filament-flex-fields::default.schema.settings.key_value_help')),
            ],
            FieldType::Repeater => [
                Placeholder::make('type_settings_repeater_help')
                    ->label('')
                    ->content(fn () => __('filament-flex-fields::default.schema.settings.repeater_help')),
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function coveredSettingKeys(): array
    {
        $keys = [];

        foreach (FieldType::cases() as $type) {
            $reserved = FlexFieldTypeSettingsStorage::reservedConfigKeys($type);

            foreach (array_keys($type->defaultConfig()) as $key) {
                if (! in_array($key, $reserved, true)) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
