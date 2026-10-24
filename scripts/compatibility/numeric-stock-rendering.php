<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CalculatorField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
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
    NumberStepper::make('value')->default(10),
    NumberStepper::make('value')->integer(false)->decimalPlaces(2)->step(0.25)->default(2.5),
    NumberStepper::make('value')->nullable()->default(null),
    NumberStepper::make('value')->locale('pl-PL')->suffixForms(['one' => 'item', 'other' => 'items'])->default(1250),
    NumberStepper::make('value')->disabled()->default(10),
    CalculatorField::make('value')->default(12.5),
    CalculatorField::make('value')->integer()->prefix('$')->default(12),
    CalculatorField::make('value')->emojiPicker()->characterCounter()->default(12),
    CalculatorField::make('value')->readOnly()->default(12),
];
foreach ($cases as $field) {
    TestableTranslatableForm::$formSchema = [$field->label('Numeric field')->helperText('Help')->hint('Hint')];
    $form = Livewire::test(TestableTranslatableForm::class);
    $field = $form->instance()->getSchema('form')->getComponents()[0];
    $stock = app(StockFieldRenderer::class)->render($field);
    if ($stock === null || $stock !== $field->render()->render()) {
        throw new RuntimeException('Numeric stock/public-context parity failed: '.$field::class);
    }
    $field->render()->with('getLabel', fn (): string => 'Custom label');
    if (! str_contains($field->toHtml(), 'Custom label')) {
        throw new RuntimeException('Customized native render view was bypassed.');
    }
}
foreach ([NumberStepper::make('value'), CalculatorField::make('value')] as $field) {
    TestableTranslatableForm::$formSchema = [$field];
    $form = Livewire::test(TestableTranslatableForm::class);
    $field = $form->instance()->getSchema('form')->getComponents()[0];
    $view = $field instanceof NumberStepper
        ? 'filament-flex-fields::forms.components.partials.number-stepper.display'
        : 'filament-flex-fields::forms.components.calculator.panel';
    app('view')->composer($view, static function (): void {});
    if (app(StockFieldRenderer::class)->render($field) !== null) {
        throw new RuntimeException('Composed numeric partial did not preserve full-context fallback.');
    }
}
echo "PASS: numeric stock/full-context parity, decimal/null/locale/disabled/tools, custom render data and late composer fallback.\n";
