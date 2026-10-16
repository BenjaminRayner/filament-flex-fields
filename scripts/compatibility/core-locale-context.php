<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogResolver;
use Bjanczak\FilamentFlexFields\Support\Icons\IconSvgCache;
use Bjanczak\FilamentFlexFields\Support\Locale\FlexFieldsLocale;
use Bjanczak\FilamentFlexFields\Support\Locale\LocaleContext;
use Bjanczak\FilamentFlexFields\Support\Locale\LocaleIdentifier;
use Bjanczak\FilamentFlexFields\Support\Translations;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

require __DIR__.'/core-framework-host.php';
function localeCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function localeRejected(Closure $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException('Malformed locale context accepted.');
}
$ja = new LocaleContext('ja-JP', ['ja', 'en']);
$sr = new LocaleContext('sr-latn-rs', ['sr', 'sr-Latn', 'en']);
$ar = new LocaleContext('ar-EG', ['ar', 'en']);
localeCheck($ja->formatting->php === 'ja_JP' && $ja->formatting->tag === 'ja-JP' && $ja->translationChain === ['ja', 'en'], 'Japanese formatting was replaced with UI fallback.');
localeCheck($sr->formatting->php === 'sr_Latn_RS' && $sr->translationChain === ['sr_Latn', 'sr', 'en'], 'Script/region fallback order changed.');
localeCheck($ar->direction === 'rtl' && (new LocaleContext('ar-Latn'))->direction === 'ltr' && (new LocaleContext('az-Arab'))->direction === 'rtl', 'Script direction is not instance-specific.');
localeCheck((new LocaleContext('ar', direction: 'ltr'))->direction === 'ltr', 'Explicit direction ignored.');
localeCheck(FlexFieldsLocale::resolve('xx') === 'en' && FlexFieldsLocale::resolve('pt-br') === 'pt_BR', 'Existing bundled UI resolution contract changed.');
localeCheck(FlexFieldsLocale::formattingLocale('de-CH') === 'de_CH' && (new LocaleIdentifier('es-419'))->php === 'es_419', 'Valid unbundled formatting locale rejected.');
localeRejected(fn () => new LocaleContext('../ar'));
localeRejected(fn () => new LocaleContext('en_US@bad'));
localeRejected(fn () => new LocaleContext('en', ['ja'], 'en'));
localeRejected(fn () => new LocaleContext('en', ['en'], direction: 'auto'));
localeRejected(fn () => new LocaleIdentifier('en_US_US'));
localeRejected(fn () => new LocaleIdentifier('sl-rozaj-rozaj'));
localeRejected(fn () => new LocaleIdentifier(str_repeat('a', 70)));
foreach (['i-klingon', 'zh-cmn-Hans-CN', 'x-author-private'] as $unsupported) {
    localeRejected(fn () => new LocaleIdentifier($unsupported));
}
$extended = new LocaleIdentifier('en-US-u-nu-arab-x-author');
localeCheck($extended->candidates() === ['en_US_u_nu_arab_x_author', 'en_US', 'en'], 'Extension fallback produced dangling subtags.');
echo "PASS: formatting beyond bundled languages, canonical script/region IDs, bounded catalogs/fallbacks, explicit and script-aware RTL.\n";

$translator = app('translator');
$translator->addLines(['fields.title' => 'Title :number', 'fields.alt' => 'Alternative text'], 'en', 'external-author');
$translator->addLines(['fields.title' => '日本語 :number'], 'ja', 'external-author');
$translator->addLines(['fields.title' => 'عنوان :number'], 'ar', 'external-author');
$translator->addLines(['fields.title' => 'Wrong application fallback'], 'fr', 'external-author');
$translator->setFallback('fr');
$appLocale = app()->getLocale();
localeCheck(Translations::forContext('external-author::fields.title', $ja, ['number' => 2]) === '日本語 2', 'External namespaced translation not used.');
localeCheck(Translations::forContext('external-author::fields.alt', $ja) === 'Alternative text', 'Explicit context fallback not used.');
localeCheck(Translations::forContext('external-author::fields.title', $ar, ['number' => 3]) === 'عنوان 3', 'Second context reused the first context.');
localeCheck(Translations::forContext('external-author::fields.missing', $ja) === 'external-author::fields.missing', 'Missing key was replaced with arbitrary content.');
localeCheck(app()->getLocale() === $appLocale, 'Context changed mutable application locale.');
if (extension_loaded('intl')) {
    $day = new DateTimeImmutable('2026-10-10T12:00:00Z');
    localeCheck(FlexFieldsLocale::formatDate($day, 'ja-JP') !== FlexFieldsLocale::formatDate($day, 'en'), 'Date formatting still follows bundled translations.');
    localeCheck(str_contains(FlexFieldsLocale::formatMoney('1234.50', 'JPY', 'ja-JP'), '1,234'), 'Unbundled locale money formatting failed.');
    echo "PASS: actual ICU date and money formatting beyond bundled UI languages.\n";
} else {
    localeCheck(FlexFieldsLocale::formatDate(new DateTimeImmutable('2026-10-10'), 'ja-JP') === '2026-10-10', 'Missing intl fallback failed.');
    echo "PASS: intl is unavailable; ISO fallback verified, ICU formatting not claimed.\n";
}
echo "PASS: real external translation namespace, replacement values and explicit key fallback; no global mutation.\n";
app()->setLocale('ja');
localeCheck((new LocaleContext)->formatting->php === 'ja', 'New context did not resolve current application locale.');
app()->setLocale('ar');
app()->forgetScopedInstances();
localeCheck((new LocaleContext)->formatting->php === 'ar' && $ja->formatting->php === 'ja_JP', 'Scope/locale change mutated an existing instance.');
echo "PASS: independent immutable contexts survive subsequent framework locale/scope changes.\n";

// Icon providers register before ViewFactory is resolved, as in a native host.
Application::create(null, null, ['extra' => ['providers' => [
    BladeIconsServiceProvider::class,
    SupportServiceProvider::class,
    FilamentFlexFieldsServiceProvider::class,
    LivewireServiceProvider::class,
]]]);
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, (string) $error);
    exit(1);
});
$icons = app(IconCatalogResolver::class);
localeCheck($icons->resolveSetNames(['nonexistent-author-set']) === [], 'Missing optional icon set was admitted as an installed provider.');
$svgCache = app(IconSvgCache::class);
$missingCalls = 0;
for ($attempt = 0; $attempt < 2; $attempt++) {
    $result = $svgCache->rememberMany(['nonexistent-author-icon'], static function (array $names) use (&$missingCalls): array {
        $missingCalls++;

        return [];
    });
    localeCheck($result === [], 'Missing icon created arbitrary fallback SVG markup.');
}
localeCheck($missingCalls === 2, 'A missing optional icon poisoned its cache with a permanent negative result.');
echo "PASS: missing optional icon set is empty; absent SVG stays explicit and is not negatively cached.\n";
