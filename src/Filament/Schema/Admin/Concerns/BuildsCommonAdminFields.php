<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schema\Admin\Concerns;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

trait BuildsCommonAdminFields
{
    protected static function settingsPrefix(): string
    {
        return 'type_settings';
    }

    protected static function settingsPath(string $key): string
    {
        return self::settingsPrefix().'.'.$key;
    }

    /**
     * @return list<Select|TextInput|Toggle>
     */
    protected static function sizeVariantFields(): array
    {
        return [
            Select::make(self::settingsPath('size'))
                ->label(fn () => __('filament-flex-fields::default.schema.settings.size'))
                ->options([
                    'xs' => 'XS',
                    'sm' => 'SM',
                    'md' => 'MD',
                    'lg' => 'LG',
                    'xl' => 'XL',
                ])
                ->native(false),
            Select::make(self::settingsPath('variant'))
                ->label(fn () => __('filament-flex-fields::default.schema.settings.variant'))
                ->options(fn () => [
                    'default' => __('filament-flex-fields::default.schema.settings.variant_default'),
                    'primary' => __('filament-flex-fields::default.schema.settings.variant_primary'),
                    'bordered' => __('filament-flex-fields::default.schema.settings.variant_bordered'),
                ])
                ->native(false),
        ];
    }

    /**
     * @return list<TextInput>
     */
    protected static function minMaxStepFields(): array
    {
        return [
            TextInput::make(self::settingsPath('min'))
                ->label(fn () => __('filament-flex-fields::default.schema.settings.min'))
                ->numeric(),
            TextInput::make(self::settingsPath('max'))
                ->label(fn () => __('filament-flex-fields::default.schema.settings.max'))
                ->numeric(),
            TextInput::make(self::settingsPath('step'))
                ->label(fn () => __('filament-flex-fields::default.schema.settings.step'))
                ->numeric(),
        ];
    }

    protected static function toggleSetting(string $key, string $label): Toggle
    {
        return Toggle::make(self::settingsPath($key))
            ->label($label)
            ->inline(false);
    }
}
