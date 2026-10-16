<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;

// Basic — searchable country picker, E.164 validation
PhoneField::make('phone')
    ->label('Phone number')
    ->defaultCountry('PL')
    ->required();

// Limited countries + mobile only
PhoneField::make('phone')
    ->label('Limited countries')
    ->countries(['PL', 'US', 'DE', 'GB', 'FR'])
    ->defaultCountry('PL')
    ->mobileOnly();

// Browser locale default + sort-first
PhoneField::make('phone')
    ->label('Browser locale')
    ->browserLocaleDefault()
    ->browserLocaleSortFirst();

// Localized country names
PhoneField::make('phone')
    ->countries(['PL', 'US', 'DE'])
    ->locale('pl');

// Fixed line only / except countries / non-searchable / read-only
PhoneField::make('phone')->defaultCountry('PL')->fixedLineOnly();
PhoneField::make('phone')->defaultCountry('PL')->exceptCountries(['RU', 'BY', 'CN']);
PhoneField::make('phone')->defaultCountry('PL')->searchable(false);
PhoneField::make('phone')->defaultCountry('PL')->readOnly();

// Opt-in storage / validation (P2)
PhoneField::make('phone')->defaultCountry('PL')->nationalDigitsOnly();
PhoneField::make('phone')->defaultCountry('PL')->validateForRegion();
PhoneField::make('phone')
    ->defaultCountry('PL')
    ->includeFormats(['international', 'rfc3966'])
    ->includeMetadata(['carrier', 'geo', 'timezones', 'type']);
PhoneField::make('phone')
    ->defaultCountry('US')
    ->allowTypes(['VOIP', 'MOBILE']);

// Presentation
PhoneField::make('phone')->defaultCountry('FR')->internationalPrefix(false);
PhoneField::make('phone')->defaultCountry('PL')->suffixIcon(FlexIcon::Handset);
PhoneField::make('phone')->size('sm')->defaultCountry('DE');
PhoneField::make('phone')->variant('secondary')->defaultCountry('PL');
PhoneField::make('phone')->variant('soft')->defaultCountry('PL');
PhoneField::make('phone')->size('lg')->defaultCountry('GB');
