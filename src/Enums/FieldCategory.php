<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Enums;

use Bjanczak\FilamentFlexFields\Support\Translations;

enum FieldCategory: string
{
    case Text = 'text';
    case Number = 'number';
    case Choice = 'choice';
    case DateTime = 'datetime';
    case Media = 'media';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Text => Translations::get('filament-flex-fields::default.categories.text'),
            self::Number => Translations::get('filament-flex-fields::default.categories.number'),
            self::Choice => Translations::get('filament-flex-fields::default.categories.choice'),
            self::DateTime => Translations::get('filament-flex-fields::default.categories.datetime'),
            self::Media => Translations::get('filament-flex-fields::default.categories.media'),
            self::Advanced => Translations::get('filament-flex-fields::default.categories.advanced'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Text => 'hugeicons-text-align-left',
            self::Number => 'hugeicons-calculator-01',
            self::Choice => 'hugeicons-left-to-right-list-bullet',
            self::DateTime => 'hugeicons-calendar-03',
            self::Media => 'hugeicons-image-01',
            self::Advanced => 'hugeicons-code-square',
        };
    }
}
