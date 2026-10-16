<?php

declare(strict_types=1);
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect\UserSelectRuntimeState;
use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyAmountNormalizer as Amount;
use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use Bjanczak\FilamentFlexFields\Support\Select\Search\SearchResultCache;
use Composer\InstalledVersions;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
Container::getInstance()->instance('config', new Repository(['filament-flex-fields' => []]));
function check(bool $condition, string $name): void
{
    if (! $condition) {
        throw new RuntimeException($name);
    }
}
function rejected(Closure $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Invalid state accepted');
}
set_error_handler(function ($severity, $message) {
    throw new ErrorException($message, 0, $severity);
});
$country = (new CountryField('country'))->countries([
    ' pl ',
    'PL',
    'US',
])->defaultCountry('PL');
check($country->getAllowedCountryCodes() === ['PL', 'US'], 'Country restriction normalization');
check($country->normalizeState('pl') === 'PL' && $country->normalizeState(['PL']) === null, 'Country structured state');
rejected(fn () => $country->countries(fn () => ['PL', []])->getAllowedCountryCodes());
$timezone = (new TimezoneField('zone'))->timezones([
    ' UTC ',
    'Europe/Warsaw',
    'UTC',
]);
check($timezone->getAllowedTimezoneIdentifiers() === ['UTC', 'Europe/Warsaw'], 'Timezone restriction normalization');
check($timezone->normalizeState(['UTC']) === null, 'Timezone structured state');
$phone = (new PhoneField('phone'))->countries(['US'])->defaultCountry('US')->locale('en');
$canonical = $phone->normalizeState([
    'country' => 'US',
    'national' => '2025550123',
    'e164' => '+442079460018',
]);
check($canonical['e164'] === '+12025550123', 'Submitted E.164 differs from canonical number');
rejected(fn () => $phone->normalizeState(['country' => 'US', 'national' => ['bad']]));
$tags = new TagsField('tags');
check($tags->normalizeTagsState([
    ' Laravel ',
    123,
    '',
]) === ['Laravel', '123'], 'Tags scalar state');
rejected(fn () => $tags->normalizeTagsState([['bad']]));
foreach ([
    [
        125,
        2,
        125,
    ],
    [
        '125',
        2,
        125,
    ],
    [
        '000125',
        2,
        125,
    ],
    [
        '12.345',
        2,
        1235,
    ],
    [
        '-12.345',
        2,
        -1235,
    ],
    [
        '.005',
        2,
        1,
    ],
    [
        '12.4',
        0,
        12,
    ],
    [
        '12.5',
        0,
        13,
    ],
    [
        '1.2345',
        3,
        1235,
    ],
    [
        '90071992547409.91',
        2,
        9007199254740991,
    ],
] as [$amount, $decimals, $expected]) {
    check(Amount::normalize($amount, $decimals) === $expected, 'Exact amount '.json_encode($amount));
}
foreach ([
    INF,
    NAN,
    '1e3',
    '1.2e3',
    [],
    true,
    9007199254740992,
    '90071992547409.915',
] as $value) {
    rejected(fn () => Amount::normalize($value, 2));
}
check(CurrencyCountries::toMinorUnits('12.345', 'USD') === 1235 && CurrencyCountries::toMinorUnits(12, 'USD') === 1200, 'Major-unit bound conversions');
$currency = new CurrencyField('price');
check($currency->normalizeState('12.345') === 1235, 'Currency field exact decimal');
rejected(fn () => $currency->normalizeState('1e100'));
rejected(fn () => $currency->currencies(['USD', []])->getAllowedCurrencyCodes());
rejected(fn () => IdentifierList::normalize(['UTC', new stdClass]));
check(IdentifierList::normalize([]) === [], 'Explicit empty restrictions');
$cache = new SearchResultCache(2, 200);
$cache->put('a', ['x' => 'a']);
$cache->put('b', ['x' => 'b']);
$cache->get('a');
$cache->put('c', ['x' => 'c']);
check($cache->get('b') === null && $cache->get('a') !== null, 'Search LRU recency');
check($cache->put('large', ['x' => str_repeat('x', 1000)]) === ['x' => str_repeat('x', 1000)] && $cache->get('large') === null, 'Oversized result returned without retention');
$state = new UserSelectRuntimeState(2, 200);
$state->synchronizeContext('tenant-a');
$state->rememberSearchResults('a', ['a' => ['label' => 'a']]);
$state->rememberSearchResults('b', ['b' => ['label' => 'b']]);
$state->cachedSearchResults('a');
$state->rememberSearchResults('c', ['c' => ['label' => 'c']]);
check($state->cachedSearchResults('b') === null && $state->cachedSearchResults('a') !== null, 'User search LRU');
$state->rememberSearchResults('huge', ['h' => ['label' => str_repeat('x', 1000)]]);
check($state->cachedSearchResults('huge') === null, 'User cache byte budget');
$state->synchronizeContext('tenant-b');
check($state->cachedSearchResults('a') === null, 'User cache context isolation');
echo 'PASS: Filament '.InstalledVersions::getPrettyVersion('filament/forms').'; Country/Timezone/Phone/Tags/Currency state boundaries, exact decimal and safe ranges, empty/malformed catalog policy, byte budgets and LRU/context isolation.'.PHP_EOL;
