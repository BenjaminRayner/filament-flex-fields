<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyBounds;
use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyCatalog;
use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyState;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI-only probe.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
Container::getInstance()->instance('config', new Repository(['filament-flex-fields' => []]));
function ensure(bool $value, string $message): void
{
    if (! $value) {
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
    throw new RuntimeException('Invalid configuration accepted.');
}
$fixed = CurrencyCatalog::resolve('USD', null);
$choice = CurrencyCatalog::resolve('EUR', ['USD', 'eur', 'EUR', 'JPY']);
ensure($choice->codes === ['EUR', 'JPY', 'USD'] && $choice->default === 'EUR', 'Catalog identity/dedup/order');
ensure(CurrencyState::normalize(['amount' => '12.50', 'currency' => 'JPY'], $fixed) === 1250, 'Fixed precision must not be controlled by submitted currency');
ensure(CurrencyState::normalize(['amount' => '12.50', 'currency' => 'JPY'], $choice) === ['amount' => 13, 'currency' => 'JPY'], 'Selected currency precision');
ensure(CurrencyState::normalize(['amount' => 9007199254740991, 'currency' => 'USD'], $choice)['amount'] === 9007199254740991, 'Exact transport maximum');
$bounds = CurrencyBounds::resolve('90071992547409.91', null);
ensure($bounds->minimumMinor('EUR') === 9007199254740991, 'Exact decimal string bounds');
reject(fn () => CurrencyBounds::resolve(20, 10));
reject(fn () => CurrencyBounds::resolve('1e2', null));
reject(fn () => CurrencyCatalog::resolve('invalid', null));
reject(fn () => CurrencyCatalog::resolve('EUR', []));
$field = (new CurrencyField('price'))->currency('EUR')->currencies(['JPY', 'EUR'])->min('12.345');
ensure($field->getMinMinorUnits('EUR') === 1235 && $field->getMinMinorUnits('JPY') === 12, 'Per-currency bound precision');
$field->locale(fn () => '');
ensure($field->getLocale('EUR') === 'de-DE', 'Blank locale uses currency locale');
echo "PASS: evaluated catalog restrictions, fixed/selected precision authority, exact decimal-string bounds, transport range and invalid configuration rejection.\n";
