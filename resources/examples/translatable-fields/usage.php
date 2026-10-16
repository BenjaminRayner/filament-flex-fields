<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;

// Each database attribute is a JSON column cast to array on the model.
TranslatableFields::make('Content')
    ->locales(['pl' => 'Polski', 'en' => 'English'])
    ->withRecommendedDefaults()
    ->schema([
        FlexTextInput::make('title')->label('Title')->required(),
        FlexTextareaField::make('body')->label('Body'),
    ]);

// One field can use the same composition API through its macro.
FlexTextInput::make('summary')->translatableFields(['en', 'pt_BR']);
