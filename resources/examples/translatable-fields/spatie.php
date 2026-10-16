<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;

// Optional: composer require spatie/laravel-translatable:^6.0
// The Eloquent model must use Spatie\Translatable\HasTranslations and declare:
// public array $translatable = ['title', 'meta->description'];
// Both `title` and `meta` are JSON database columns.
TranslatableFields::make('Translations')
    ->spatieTranslatable()
    ->locales(['en' => 'English', 'pl' => 'Polski', 'pt_BR' => 'Português'])
    ->withRecommendedDefaults()
    ->schema([
        FlexTextInput::make('title')->required(),
        FlexTextInput::make('meta->description')->label('Description'),
    ]);

// To substitute the input renderer while retaining automatic locale paths:
TranslatableFields::make('Custom renderer')
    ->locales(['en', 'pl'])
    ->localeFieldUsing(
        fn ($template) => FlexTextInput::make('renderer')->rounding('full'),
        useTemplateStatePath: true,
    )
    ->schema([FlexTextInput::make('title')]);
