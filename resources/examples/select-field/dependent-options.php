<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('region')
    ->dependsOn('country', fn (?string $country): array => match ($country) {
        'pl' => ['mz' => 'Mazowieckie', 'wp' => 'Wielkopolskie'],
        default => [],
    })
    ->searchable();

SelectField::make('city')
    ->options($arabicCities)
    ->searchable()
    ->inlineSearch()
    ->clearable()
    ->extraAttributes(['dir' => 'rtl']);
