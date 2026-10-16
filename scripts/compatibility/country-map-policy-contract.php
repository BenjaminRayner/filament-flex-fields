<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryMapField;
use Bjanczak\FilamentFlexFields\Support\CountryMap\CountryMapCamera;
use Bjanczak\FilamentFlexFields\Support\CountryMap\CountryMapCatalog;
use Bjanczak\FilamentFlexFields\Support\CountryMap\CountryMapGeometry;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI-only probe.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
Container::getInstance()->instance('config', new Repository(['filament-flex-fields' => []]));
function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function reject(Closure $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Invalid country map policy accepted.');
}
$catalog = CountryMapCatalog::resolve(['pl', 'US', 'PL'], ['Group' => ['us', 'PL', 'US']]);
ensure($catalog->codes === ['PL', 'US'] && $catalog->groups === ['Group' => ['US', 'PL']], 'ISO authority and group dedup');
ensure($catalog->accepts(['PL', 'US'], true) && $catalog->accepts('PL', false), 'Selection modes');
foreach ([['PL', 'PL'], ['DE'], ['pl'], ['PL', []], ['key' => 'PL'], 'PL'] as $state) {
    ensure(! $catalog->accepts($state, true), 'Forged state rejected');
}
ensure(CountryMapCatalog::resolve([])->accepts([], true), 'Empty restriction remains empty');
reject(fn () => CountryMapCatalog::resolve(['ZZ']));
reject(fn () => CountryMapCatalog::resolve(['PL'], ['Outside' => ['US']]));
reject(fn () => CountryMapCatalog::resolve(['PL'], ['A' => [], ' A ' => []]));
reject(fn () => CountryMapCamera::resolve(0, 6, 180, false, true));
reject(fn () => CountryMapCamera::resolve(4, 2, 180, false, true));
reject(fn () => CountryMapCamera::resolve(1, 6, 1001, false, true));
reject(fn () => CountryMapCamera::resolve(1, 6, 180, 'false', true));
$field = (new CountryMapField('countries'))->countries(['PL', 'US'])->countryGroups(['Team' => ['PL']])->mapZoomRange(2, 8)->mapAnimationDuration(0);
ensure($field->getCountryGroups() === ['Team' => ['PL']] && $field->getCountryMapCamera()['minZoom'] === 2, 'Public evaluated field configuration');
ensure(CountryMapGeometry::paths() === CountryMapGeometry::paths() && isset(CountryMapGeometry::paths()['PL']), 'Immutable geometry cache');
echo "PASS: canonical ISO authority, restricted/duplicate/forged state, bounded groups, strict camera configuration and immutable map geometry.\n";
