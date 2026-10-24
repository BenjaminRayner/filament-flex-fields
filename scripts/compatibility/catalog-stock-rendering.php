<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Rendering\StockFieldRenderer;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

$app = Application::create(null, null, ['extra' => ['providers' => [
    TranslationServiceProvider::class,
    BladeIconsServiceProvider::class,
    BladeHeroiconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class,
    FilamentFlexFieldsServiceProvider::class,
    FilamentServiceProvider::class,
    SupportServiceProvider::class,
    LivewireServiceProvider::class,
    FormsServiceProvider::class,
    SchemasServiceProvider::class,
    ActionsServiceProvider::class,
    TablesServiceProvider::class,
]]]);
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
$app['config']->set('cache.default', 'array');

$cases = [
    CountryField::make('value')->default('PL'),
    TimezoneField::make('value')->default('Europe/Warsaw'),
    PhoneField::make('value')->default(['country' => 'PL', 'national' => '512345678']),
    CurrencyField::make('value')->currency('EUR')->currencies(['EUR', 'USD'])->default(['amount' => 125050, 'currency' => 'EUR']),
    FontSelect::make('value')->default(null),
    IconPickerField::make('value')->sets(['heroicons'])->default('heroicon-o-star'),
];
foreach ($cases as $field) {
    TestableTranslatableForm::$formSchema = [$field->label('Catalog field')->helperText('Help')->hint('Hint')];
    $form = Livewire::test(TestableTranslatableForm::class);
    $field = $form->instance()->getSchema('form')->getComponents()[0];
    $stock = app(StockFieldRenderer::class)->render($field);
    if ($stock === null || $stock !== $field->render()->render()) {
        throw new RuntimeException('Catalog stock/public-context parity failed: '.$field::class.($stock === null ? ' (fallback)' : ' (markup mismatch)'));
    }
    $field->render()->with('getExtraAttributeBag', fn () => new ComponentAttributeBag(['data-custom-probe' => 'retained']));
    if (! str_contains($field->toHtml(), 'data-custom-probe')) {
        throw new RuntimeException('Customized native render view was bypassed.');
    }
    echo 'PASS: stock/full-context and custom native data: '.$field::class."\n";
}
foreach ($cases as $field) {
    TestableTranslatableForm::$formSchema = [clone $field];
    $form = Livewire::test(TestableTranslatableForm::class);
    $field = $form->instance()->getSchema('form')->getComponents()[0];
    app('view')->composer($field->getView(), static function (): void {});
    if (app(StockFieldRenderer::class)->render($field) !== null) {
        throw new RuntimeException('Composed catalog root did not preserve full-context fallback.');
    }
}
echo "PASS: late composers retain full context.\n";
