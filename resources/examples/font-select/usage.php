<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;

// Saves only a stable family ID; preview fonts load only for visible dropdown rows.
FontSelect::make('heading_font')
    ->previewSelectedFont(false)
    ->default('google:inter')
    ->required();

FontSelect::make('brand_font')
    ->previewSelectedFont(false)
    ->fonts(['google:inter', 'google:roboto', 'google:lora'])
    ->previewText('Your brand, beautifully set.')
    ->previewWeight(600)
    ->categoryTabs(['sans-serif', 'serif'])
    ->perPage(48);

FontSelect::make('editorial_font')
    ->previewSelectedFont(false)
    ->categories(['serif'])->subsets(['latin-ext'])->variableOnly()
    ->sortFonts('alpha')->size('lg')->rounding('xl');

// Host your licensed fonts on the same origin; the picker never removes your CSS fonts.
FontSelect::make('local_font')->previewSelectedFont(false)->providers(['local'])->localFonts([
    'brand' => ['family' => 'Brand Sans', 'category' => 'sans-serif',
        'variants' => ['400', '700'], 'url' => '/fonts/brand-regular.woff2'],
]);
