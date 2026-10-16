<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Countries;
use Bjanczak\FilamentFlexFields\Support\CountryFlags;
use Bjanczak\FilamentFlexFields\Support\CountryRegistry;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators\CountryFieldConfigurator;
use Bjanczak\FilamentFlexFields\Support\PhoneCountries;
use Illuminate\Support\Facades\Validator;
use libphonenumber\PhoneNumberUtil;

it('ships a safe materialized SVG for every ISO and supported phone region', function () {
    $directory = dirname(__DIR__, 2).'/resources/images/country-flags';
    $codes = [...Countries::allCodes(), ...Countries::ADDITIONAL_REGION_CODES];
    $provenance = json_decode(file_get_contents($directory.'/source.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($codes)->toHaveCount(255)
        ->and(array_diff(PhoneCountries::allSupportedCodes(), $codes))->toBe([]);

    foreach ($codes as $code) {
        $file = $directory.'/'.strtolower($code).'.svg';
        $svg = file_get_contents($file);
        $document = new DOMDocument;

        expect(is_link($file))->toBeFalse()
            ->and($document->loadXML($svg, LIBXML_NONET))->toBeTrue()
            ->and($document->documentElement->localName)->toBe('svg')
            ->and($document->documentElement->getAttribute('viewBox'))->toBe('0 0 512 512')
            ->and(hash('sha256', $svg))->toBe($provenance['flags'][$code]['sha256']);

        foreach ($document->getElementsByTagName('*') as $node) {
            expect(in_array($node->localName, ['script', 'foreignObject', 'image', 'use'], true))->toBeFalse();
            foreach ($node->attributes as $attribute) {
                expect(str_starts_with(strtolower($attribute->name), 'on'))->toBeFalse()
                    ->and(preg_match('~(?:https?:|javascript:|data:|url\((?!#))~i', $attribute->value))->toBe(0);
            }
        }
    }
});

it('registers one publication owner composed by both country and phone fields', function () {
    $registry = ComponentAssetCatalog::registry();
    $owner = $registry->definition('country-flags');

    expect($owner->entry)->toBeNull()
        ->and($owner->styles)->toBe([])
        ->and($owner->publications)->toHaveCount(1)
        ->and($registry->definition('country-field')->uses)->toContain('country-flags')
        ->and($registry->definition('phone-field')->uses)->toContain('country-flags');

    foreach (PhoneCountries::allSupportedCodes() as $code) {
        expect(Countries::flagUrl($code))->toBe(PhoneCountries::flagUrl($code))
            ->and(PhoneCountries::flagUrl($code))->toContain(url('/'))
            ->not->toContain('jsdelivr', '@latest');
    }
});

it('rejects arbitrary or path-like flag identities', function (string $code) {
    CountryFlags::url($code);
})->with(['ZZ', '../pl', 'PL.svg', ''])->throws(InvalidArgumentException::class);

it('keeps ISO membership stable across locales and adds only explicitly enabled regions', function () {
    $base = CountryField::make('country');
    $extended = CountryField::make('country')->additionalRegions(['xk', 'XK', 'ZZ']);

    expect($base->getResolvedCountryCodes())->toHaveCount(249)
        ->and($base->getResolvedCountryCodes())->not->toContain('XK', 'IC')
        ->and($extended->getResolvedCountryCodes())->toHaveCount(250)
        ->and($extended->getResolvedCountryCodes())->toContain('XK')
        ->and($extended->getResolvedCountryCodes())->not->toContain('IC', 'ZZ')
        ->and($extended->getCountryPool())->toBe(CountryRegistry::POOL_EXTENDED)
        ->and($extended->getCountryFilterKey())->not->toBeNull()
        ->and(CountryField::make('country')->locale('ar')->getResolvedCountryCodes())->toBe($base->getResolvedCountryCodes());

    expect(Validator::make(['country' => 'XK'], ['country' => $base->getValidationRules()])->passes())->toBeFalse()
        ->and(Validator::make(['country' => 'XK'], ['country' => $extended->getValidationRules()])->passes())->toBeTrue();
});

it('shares translated ICU names without falling back prematurely to English', function () {
    app()->setLocale('en');
    expect(Countries::name('PL', 'fr'))->toBe('Pologne')
        ->and(PhoneCountries::name('PL', 'fr'))->toBe('Pologne')
        ->and(Countries::name('PL', 'de'))->toBe('Polen')
        ->and(PhoneCountries::name('PL', 'pl'))->toBe('Polska');

    $names = CountryRegistry::poolNames(CountryRegistry::POOL_PHONE, 'fr');
    expect($names['PL'])->toBe('Pologne');
});

it('excludes blacklisted regions during phone locale detection', function () {
    app()->setLocale('pl');
    $field = PhoneField::make('phone')->countries(['PL', 'US'])->exceptCountries(['PL'])->browserLocaleDefault();

    expect($field->getBrowserLocaleCountryCode())->toBeNull()
        ->and($field->getDefaultCountryCode())->toBe('US');
});

it('applies additional regions through the schema configurator', function () {
    $configurator = app(CountryFieldConfigurator::class);
    $field = $configurator->configure(CountryField::make('country'), ['additional_regions' => ['XK']]);

    expect($field->getResolvedCountryCodes())->toHaveCount(250)
        ->and($field->getResolvedCountryCodes())->toContain('XK');
});

it('provides a flag and correct calling prefix for every supported phone region', function () {
    $util = PhoneNumberUtil::getInstance();
    foreach (PhoneCountries::allSupportedCodes() as $code) {
        $metadata = PhoneCountries::metadata([$code])[0];
        expect($metadata['dial_code'])->toBe('+'.$util->getCountryCodeForRegion($code))
            ->and($metadata['dial_code'])->not->toBe('+0')
            ->and($metadata['flag_url'])->toBe(CountryFlags::url($code));
    }
});
