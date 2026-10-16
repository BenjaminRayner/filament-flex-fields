<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Select\Catalog\PickerCatalogState;
use Bjanczak\FilamentFlexFields\Support\Timezones;

it('exposes all iana timezone identifiers', function () {
    expect(count(Timezones::allIdentifiers()))->toBeGreaterThan(400)
        ->and(Timezones::allIdentifiers())->toContain('Europe/Warsaw', 'UTC', 'America/New_York');
});

it('formats timezone labels as city and country with a separate utc offset', function () {
    expect(Timezones::label('UTC'))->toBe('UTC')
        ->and(Timezones::countryCode('UTC'))->toBeNull()
        ->and(Timezones::humanizeIdentifier('Europe/Warsaw'))->toBe('Warsaw')
        ->and(Timezones::humanizeIdentifier('America/Argentina/Buenos_Aires'))->toBe('Buenos Aires')
        ->and(Timezones::formatCityCountry('Warsaw', 'Poland'))->toBe('Warsaw, Poland')
        ->and(Timezones::formatCityCountry('Singapore', 'Singapore'))->toBe('Singapore')
        ->and(Timezones::formatOffset('Europe/Warsaw'))->toMatch('/^UTC[+-]\d{2}:\d{2}$/');
});

it('resolves localized timezone display names via intl when available', function () {
    if (! extension_loaded('intl')) {
        expect(Timezones::displayName('Europe/Warsaw'))->toBe('Warsaw');

        return;
    }

    app()->setLocale('en');

    expect(Timezones::countryCode('Europe/Warsaw'))->toBe('PL')
        ->and(Timezones::displayName('Europe/Warsaw'))->toBe('Warsaw, Poland')
        ->and(Timezones::displayName('Europe/Warsaw'))->not->toContain('Poland Time')
        ->and(Timezones::displayName('America/New_York'))->toBe('New York, United States')
        ->and(Timezones::displayName('UTC'))->toBe('UTC');

    app()->setLocale('pl');

    expect(Timezones::displayName('Europe/Warsaw'))->toBe('Warszawa, Polska')
        ->and(Timezones::displayName('Europe/Warsaw'))->not->toContain('czas: Polska');
});

it('prefers published translation overrides over intl', function () {
    app()->setLocale('en');

    app(PickerCatalogState::class)->clear();

    expect(Timezones::translationKey('Europe/Warsaw'))->toBe('Europe__Warsaw')
        ->and(Timezones::displayName('UTC'))->toBe('UTC');

    app('translator')->addLines([
        'timezones.America__Chicago' => 'Custom Chicago',
    ], 'en', 'filament-flex-fields');

    expect(Timezones::displayName('America/Chicago'))->toBe('Custom Chicago')
        ->and(Timezones::label('America/Chicago'))->toBe('Custom Chicago');
});

it('invalidates timezone metadata cache when locale changes', function () {
    if (! extension_loaded('intl')) {
        expect(true)->toBeTrue();

        return;
    }

    app()->setLocale('en');

    $english = Timezones::metadata(['Europe/Warsaw'])[0]['label'];

    app()->setLocale('pl');

    $polish = Timezones::metadata(['Europe/Warsaw'])[0]['label'];

    expect($english)->toBe('Warsaw, Poland')
        ->and($polish)->toBe('Warszawa, Polska')
        ->and($polish)->not->toBe($english);
});

it('resolves display names for an explicit locale without changing app locale', function () {
    if (! extension_loaded('intl')) {
        expect(true)->toBeTrue();

        return;
    }

    app()->setLocale('en');

    expect(Timezones::displayName('Europe/Warsaw', 'en'))->toBe('Warsaw, Poland')
        ->and(Timezones::displayName('Europe/Warsaw', 'pl'))->toBe('Warszawa, Polska')
        ->and(app()->getLocale())->toBe('en')
        ->and(Timezones::metadata(['Europe/Warsaw'], locale: 'pl')[0]['label'])->toBe('Warszawa, Polska')
        ->and(Timezones::metadata(['Europe/Warsaw'], locale: 'en')[0]['label'])->toBe('Warsaw, Poland');
});

it('applies translation overrides for the requested locale', function () {
    app(PickerCatalogState::class)->clear();

    app('translator')->addLines([
        'timezones.Europe__Warsaw' => 'Warszawa custom',
    ], 'pl', 'filament-flex-fields');

    expect(Timezones::displayName('Europe/Warsaw', 'pl'))->toBe('Warszawa custom')
        ->and(Timezones::displayName('Europe/Warsaw', 'en'))->not->toBe('Warszawa custom');
});

it('resolves timezone whitelist and blacklist', function () {
    expect(Timezones::resolve(['Europe/Warsaw', 'UTC', 'Invalid/Zone']))
        ->toBe(['Europe/Warsaw', 'UTC']);

    expect(Timezones::resolve(null, ['Etc/GMT+1']))
        ->not->toContain('Etc/GMT+1');
});

it('builds timezone metadata and select options', function () {
    $metadata = Timezones::metadata(['Europe/Warsaw', 'UTC']);

    expect($metadata)->toHaveCount(2)
        ->and($metadata[0])->toHaveKeys(['id', 'label', 'offset', 'offset_seconds', 'region'])
        ->and(Timezones::selectOptions(['UTC']))->toHaveKey('UTC');
});

it('sorts preferred timezone first in metadata list', function () {
    $metadata = Timezones::metadata(['Europe/Warsaw', 'UTC', 'America/New_York']);
    $sorted = Timezones::sortWithPreferredFirst($metadata, 'UTC');

    expect($sorted[0]['id'])->toBe('UTC');
});

it('maps browser timezone candidates to allowed identifiers', function () {
    expect(Timezones::fromBrowserTimezoneCandidates(['Europe/Warsaw', 'Invalid/Zone'], ['Europe/Warsaw', 'UTC']))
        ->toBe('Europe/Warsaw');
});

it('invalidates cached timezone offsets when the minute changes', function () {
    $cache = app(PickerCatalogState::class);
    $cache->synchronizeTimezoneInstant(0);
    $cache->timezoneMetadata[app()->getLocale()]['UTC'] = [
        'id' => 'UTC', 'label' => 'Stale label', 'offset' => 'UTC+01:00',
        'offset_seconds' => 3600, 'region' => 'UTC',
    ];
    $metadata = Timezones::metadata(['UTC']);
    expect($metadata[0]['label'])->toBe('UTC')
        ->and($metadata[0]['offset'])->toBe('UTC+00:00');
});

it('lazily resolves and caches timezone metadata on demand', function () {
    $cache = app(PickerCatalogState::class);
    $cache->clear();
    $metadata = Timezones::metadata(['UTC']);
    expect($metadata)->toHaveCount(1)
        ->and($cache->timezoneMetadata[app()->getLocale()])->toHaveKey('UTC')
        ->and($cache->timezoneMetadata[app()->getLocale()])->not->toHaveKey('Europe/Warsaw');
});
