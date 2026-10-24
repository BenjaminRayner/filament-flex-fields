<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
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

foreach (['plain', 'tools', 'select', 'readonly'] as $mode) {
    $field = FlexTextareaField::make('value')->label('Textarea')->helperText('Help')->hint('Hint');
    match ($mode) {
        'tools' => $field->emojiPicker()->speechDictation()->characterCounter(),
        'select' => $field->toolbarSelect('format', ['a' => 'Alpha', 'b' => 'Beta']),
        'readonly' => $field->readOnly(),
        default => $field,
    };
    TestableTranslatableForm::$formSchema = [$field];
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['value' => "Long note\nSecond line", 'format' => 'a']);
    $field = $form->instance()->getSchema('form')->getComponents()[0];
    $stock = app(StockFieldRenderer::class)->render($field);
    if ($stock === null || $stock !== $field->render()->render()) {
        throw new RuntimeException('Textarea stock/public-context parity failed: '.$mode);
    }
}
$field->render()->with('getLabel', fn (): string => 'Custom label');
if (! str_contains($field->toHtml(), 'Custom label')) {
    throw new RuntimeException('Customized native render view was bypassed.');
}
// Escaping can exceed the metadata budget even when the source string is small.
// The optimization must leave a valid large custom label on the inline path.
TestableTranslatableForm::$formSchema = [FlexTextareaField::make('value')->speechDictationLabel(str_repeat('<&', 6000))];
$large = Livewire::test(TestableTranslatableForm::class)->html();
if (str_contains($large, 'data-fff-control-configuration-id=')) {
    throw new RuntimeException('Oversized escaped options did not keep the inline fallback.');
}
app('view')->composer('filament-flex-fields::forms.components.partials.flex-textarea-toolbar-select', static function (): void {});
if (app(StockFieldRenderer::class)->render($field) !== null) {
    throw new RuntimeException('Published/composed toolbar did not preserve the full-context fallback.');
}
echo "PASS: actual Textarea stock/public-context parity, tools, toolbar Select, readonly, custom render data and late composer fallback.\n";
