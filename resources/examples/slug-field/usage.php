<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SlugField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TitleSlugField;

// A title and slug with one shared schema. Editing the draft stays local.
TitleSlugField::make(
    fieldTitle: 'title',
    fieldSlug: 'slug',
    translatableLocales: [],
    urlHost: config('app.url'),
    urlPath: '/posts/',
);

// Compose an existing FLEX input as the title, without duplicating its renderer.
SlugField::make('slug')
    ->titleField(FlexTextInput::make('title')->label('Title'))
    ->urlHost(config('app.url'))
    ->urlPath('/articles/')
    ->maxSlugLength(120)
    ->slugRules(['required', 'string', 'max:120'])
    ->generationDebounce(250)
    ->preserveSlugOnEdit()
    ->actionButtonsIconOnly();

// A single permalink generated from the selected source locale.
TitleSlugField::make(
    fieldTitle: 'translated_title',
    fieldSlug: 'translated_slug',
    translatableLocales: ['en' => 'English', 'pl' => 'Polski'],
    slugSourceLocale: 'en',
    urlHost: config('app.url'),
    urlPath: '/guides/',
);

// Optional model integration: configure these on your real Eloquent resource.
// SlugField::make('slug')->source('title')->spatieModel(Post::class)
//     ->slugUniqueScope(fn ($query) => $query->where('tenant_id', auth()->user()->tenant_id));
